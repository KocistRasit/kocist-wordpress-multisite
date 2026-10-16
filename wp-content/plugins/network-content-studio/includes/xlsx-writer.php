<?php
/**
 * Kucuk .xlsx yazici.
 *
 * Okuyucunun (xlsx-reader.php) karsiligi: ZipArchive ile, kitaplik olmadan
 * gecerli bir Excel dosyasi uretir. Yalnizca bu eklentinin ihtiyaci:
 *   - birden cok sayfa, gizli sayfa;
 *   - hucreler metin olarak (satir ici metin + "Metin" sayi bicimi): fiyat,
 *     kod ve "0012" gibi degerler Excel'de sayiya ya da tarihe donmez;
 *   - sutun genisligi, gizli sutun, satir kaydirma;
 *   - kalin ve dondurulmus baslik satiri.
 */

defined( 'ABSPATH' ) || exit;

/**
 * 0 tabanli sutun sirasini harfe cevirir (0 -> A, 27 -> AB).
 */
function nwcs_xlsx_column_letter( int $index ): string {
	$letters = '';
	$index++;

	while ( $index > 0 ) {
		$mod     = ( $index - 1 ) % 26;
		$letters = chr( 65 + $mod ) . $letters;
		$index   = intdiv( $index - $mod, 26 );
	}

	return $letters;
}

/**
 * XML icin metin: gecersiz kontrol karakterleri atilir, kacis uygulanir.
 */
function nwcs_xlsx_escape( string $text ): string {
	$text = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );

	return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
}

/**
 * Tek sayfanin XML'i.
 *
 * @param array $sheet {
 *     rows:    array<int, array<int, string>>  ilk satir baslik sayilir
 *     columns: array<int, array{width?:float, hidden?:bool, wrap?:bool}>
 *     header:  bool  ilk satir kalin ve dondurulmus (varsayilan true)
 * }
 */
function nwcs_xlsx_sheet_xml( array $sheet ): string {
	$rows    = (array) ( $sheet['rows'] ?? array() );
	$columns = (array) ( $sheet['columns'] ?? array() );
	$header  = $sheet['header'] ?? true;
	$width   = 0;

	foreach ( $rows as $row ) {
		$width = max( $width, count( (array) $row ) );
	}

	$width = max( $width, $columns ? max( array_keys( $columns ) ) + 1 : 0, 1 );

	$xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	$xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';

	if ( $header ) {
		$xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/></sheetView></sheetViews>';
	}

	$xml .= '<sheetFormatPr defaultRowHeight="15"/>';

	// Her sutun "Metin" bicimli (stil 1 / 3): sonradan yazilan hucreler de metin kalir.
	$xml .= '<cols>';
	for ( $i = 0; $i < $width; $i++ ) {
		$col   = (array) ( $columns[ $i ] ?? array() );
		$style = ! empty( $col['wrap'] ) ? 3 : 1;
		$xml  .= sprintf(
			'<col min="%1$d" max="%1$d" width="%2$s" style="%3$d" customWidth="1"%4$s/>',
			$i + 1,
			number_format( (float) ( $col['width'] ?? 18 ), 1, '.', '' ),
			$style,
			! empty( $col['hidden'] ) ? ' hidden="1"' : ''
		);
	}
	$xml .= '</cols>';

	$xml .= '<sheetData>';

	foreach ( array_values( $rows ) as $r => $row ) {
		$xml .= '<row r="' . ( $r + 1 ) . '">';

		foreach ( array_values( (array) $row ) as $c => $value ) {
			$value = (string) $value;
			$col   = (array) ( $columns[ $c ] ?? array() );
			$style = ( $header && 0 === $r ) ? 2 : ( ! empty( $col['wrap'] ) ? 3 : 1 );
			$ref   = nwcs_xlsx_column_letter( $c ) . ( $r + 1 );

			if ( '' === $value ) {
				$xml .= '<c r="' . $ref . '" s="' . $style . '"/>';
				continue;
			}

			$xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . nwcs_xlsx_escape( $value ) . '</t></is></c>';
		}

		$xml .= '</row>';
	}

	$xml .= '</sheetData>';
	$xml .= '</worksheet>';

	return $xml;
}

/**
 * Sayfalari .xlsx dosyasina yazar.
 *
 * @param array<int, array{name:string, hidden?:bool}> $sheets nwcs_xlsx_sheet_xml() girdisi + ad.
 * @return true|WP_Error
 */
function nwcs_xlsx_write( string $path, array $sheets ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'nwcs_xlsx_zip', 'Excel dosyası bu panelde hazırlanamıyor. Bu sizin düzeltebileceğiniz bir sorun değil; geliştiriciye haber verin. (Teknik: ZipArchive yok)' );
	}

	$zip = new ZipArchive();

	if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		return new WP_Error( 'nwcs_xlsx_open', 'Excel dosyası hazırlanamadı. Yeniden deneyin; sorun sürerse geliştiriciye haber verin. (Teknik: geçici dosya açılamadı)' );
	}

	$book_sheets = '';
	$book_rels   = '';
	$types       = '';

	foreach ( array_values( $sheets ) as $i => $sheet ) {
		$n    = $i + 1;
		// Sayfa adi: en fazla 31 karakter, []:*?/\ olmadan.
		$name = mb_substr( (string) preg_replace( '#[\[\]:*?/\\\\]#', '', (string) ( $sheet['name'] ?? 'Sayfa' . $n ) ), 0, 31 );

		$book_sheets .= '<sheet name="' . nwcs_xlsx_escape( $name ) . '" sheetId="' . $n . '"' . ( ! empty( $sheet['hidden'] ) ? ' state="hidden"' : '' ) . ' r:id="rId' . $n . '"/>';
		$book_rels   .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		$types       .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';

		$zip->addFromString( 'xl/worksheets/sheet' . $n . '.xml', nwcs_xlsx_sheet_xml( $sheet ) );
	}

	$count = count( $sheets );

	$zip->addFromString(
		'[Content_Types].xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
		. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
		. '<Default Extension="xml" ContentType="application/xml"/>'
		. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
		. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
		. $types
		. '</Types>'
	);

	$zip->addFromString(
		'_rels/.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
		. '</Relationships>'
	);

	$zip->addFromString(
		'xl/workbook.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. '<bookViews><workbookView activeTab="0"/></bookViews>'
		. '<sheets>' . $book_sheets . '</sheets></workbook>'
	);

	$zip->addFromString(
		'xl/_rels/workbook.xml.rels',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. $book_rels
		. '<Relationship Id="rId' . ( $count + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
		. '</Relationships>'
	);

	// Stiller: 0 genel, 1 metin, 2 kalin metin (baslik), 3 metin + satir kaydirma.
	$zip->addFromString(
		'xl/styles.xml',
		'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
		. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
		. '<fill><patternFill patternType="solid"><fgColor rgb="FFEEF0FF"/><bgColor indexed="64"/></patternFill></fill></fills>'
		. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
		. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
		. '<cellXfs count="4">'
		. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
		. '<xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/>'
		. '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
		. '</cellXfs>'
		. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
		. '</styleSheet>'
	);

	$zip->close();

	return true;
}

/**
 * Dosyayi tarayiciya indirme olarak gonderir ve cikar.
 */
function nwcs_xlsx_send( string $path, string $filename ): void {
	nocache_headers();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	header( 'Content-Length: ' . (string) filesize( $path ) );

	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	wp_delete_file( $path );
	exit;
}
