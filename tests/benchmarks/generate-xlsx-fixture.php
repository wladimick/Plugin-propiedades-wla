<?php

declare(strict_types=1);

if ($argc < 3) {
	fwrite(STDERR, "Usage: php generate-xlsx-fixture.php <output.xlsx> <rows>\n");
	exit(2);
}

$output = (string) $argv[1];
$rows = filter_var($argv[2], FILTER_VALIDATE_INT, array('options' => array('min_range' => 1, 'max_range' => 50000)));
if (!is_int($rows)) {
	fwrite(STDERR, "Rows must be between 1 and 50000.\n");
	exit(2);
}
if (!class_exists(ZipArchive::class)) {
	fwrite(STDERR, "ZipArchive is required.\n");
	exit(2);
}

$directory = dirname($output);
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
	fwrite(STDERR, "Unable to create output directory.\n");
	exit(1);
}

$sheetPath = tempnam(sys_get_temp_dir(), 'wla-xlsx-sheet-');
if (!is_string($sheetPath)) {
	fwrite(STDERR, "Unable to create worksheet temporary file.\n");
	exit(1);
}

$sheet = fopen($sheetPath, 'wb');
if ($sheet === false) {
	@unlink($sheetPath);
	fwrite(STDERR, "Unable to open worksheet temporary file.\n");
	exit(1);
}

$xml = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
$inline = static fn (string $reference, string $value): string => '<c r="' . $reference . '" t="inlineStr"><is><t>' . $xml($value) . '</t></is></c>';
$numeric = static fn (string $reference, int $value): string => '<c r="' . $reference . '"><v>' . $value . '</v></c>';

fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
fwrite($sheet, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
fwrite(
	$sheet,
	'<row r="1">'
	. $inline('A1', 'property_code')
	. $inline('B1', 'title')
	. $inline('C1', 'price_clp')
	. $inline('D1', 'external_id')
	. $inline('E1', 'status')
	. $inline('F1', 'commune')
	. '</row>'
);

for ($index = 1; $index <= $rows; ++$index) {
	$rowNumber = $index + 1;
	$code = sprintf('BENCH-%06d', $index);
	$externalId = sprintf('EXT-%06d', $index);
	fwrite(
		$sheet,
		'<row r="' . $rowNumber . '">'
		. $inline('A' . $rowNumber, $code)
		. $inline('B' . $rowNumber, 'Propiedad sintética Ñ ' . $index)
		. $numeric('C' . $rowNumber, 100000000 + $index)
		. $inline('D' . $rowNumber, $externalId)
		. $inline('E' . $rowNumber, 'available')
		. $inline('F' . $rowNumber, 'Comuna ' . (($index % 25) + 1))
		. '</row>'
	);
}

fwrite($sheet, '</sheetData></worksheet>');
fclose($sheet);

$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
	@unlink($sheetPath);
	fwrite(STDERR, "Unable to create XLSX archive.\n");
	exit(1);
}

$zip->addFromString(
	'[Content_Types].xml',
	'<?xml version="1.0" encoding="UTF-8"?>'
	. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
	. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
	. '<Default Extension="xml" ContentType="application/xml"/>'
	. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
	. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
	. '</Types>'
);
$zip->addFromString(
	'_rels/.rels',
	'<?xml version="1.0" encoding="UTF-8"?>'
	. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
	. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
	. '</Relationships>'
);
$zip->addFromString(
	'xl/workbook.xml',
	'<?xml version="1.0" encoding="UTF-8"?>'
	. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
	. '<sheets><sheet name="Properties" sheetId="1" r:id="rId1"/></sheets>'
	. '</workbook>'
);
$zip->addFromString(
	'xl/_rels/workbook.xml.rels',
	'<?xml version="1.0" encoding="UTF-8"?>'
	. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
	. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
	. '</Relationships>'
);
$zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
$zip->close();
@unlink($sheetPath);

$bytes = filesize($output);
if (!is_int($bytes) || $bytes <= 0) {
	fwrite(STDERR, "Generated XLSX is empty.\n");
	exit(1);
}

echo json_encode(
	array(
		'rows' => $rows,
		'columns' => 6,
		'file_bytes' => $bytes,
		'sha256' => hash_file('sha256', $output),
	),
	JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
) . PHP_EOL;
