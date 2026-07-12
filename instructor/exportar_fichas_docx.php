<?php
// instructor/exportar_fichas_docx.php
require_once '../conexion.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\VerticalJc;

if (!isset($_SESSION['usuario_id']) || strtolower(trim($_SESSION['rol_nombre'] ?? '')) !== 'instructor') {
    die('Acceso denegado');
}

$idLote = isset($_GET['lote']) ? intval($_GET['lote']) : 0;
if ($idLote === 0) {
    die('Lote no válido');
}

$stmt = $pdo->prepare("SELECT * FROM ficha_tecnica ft
        INNER JOIN matriz_item mi ON ft.ID_MATRIZ_ITEM = mi.ID_MATRIZ_ITEM
        WHERE mi.ID_LOTE = ? ORDER BY ft.ID_FICHA_TECNICA ASC");
$stmt->execute([$idLote]);
$fichas = $stmt->fetchAll();

if (empty($fichas)) {
    die('No hay fichas técnicas para exportar en este lote.');
}

$tempImages = [];
function webpToPngTemp(string $webpPath): ?string
{
    global $tempImages;
    if (!function_exists('imagecreatefromwebp')) {
        return null;
    }
    $image = @imagecreatefromwebp($webpPath);
    if ($image === false) {
        return null;
    }
    $tempPath = tempnam(sys_get_temp_dir(), 'ficha_img_') . '.png';
    imagepng($image, $tempPath);
    imagedestroy($image);
    $tempImages[] = $tempPath;

    return $tempPath;
}

$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10);

$tableStyleName = 'Ficha Table';
$tableStyle = [
    'borderSize' => 6,
    'borderColor' => '000000',
    'cellMargin' => 80
];
$phpWord->addTableStyle($tableStyleName, $tableStyle);

$headerStyle = ['bgColor' => 'E0E0E0', 'valign' => VerticalJc::CENTER];
$labelStyle = ['bgColor' => 'F0F0F0', 'valign' => VerticalJc::CENTER];
$titleFont = ['bold' => true, 'size' => 12];
$centerFormat = ['alignment' => Jc::CENTER];

foreach ($fichas as $index => $ficha) {
    $section = $phpWord->addSection();
    
    $table = $section->addTable($tableStyleName);
    
    // Header
    $table->addRow();
    $cell = $table->addCell(9000, ['gridSpan' => 2, 'bgColor' => 'E0E0E0', 'valign' => VerticalJc::CENTER]);
    $cell->addText('FICHA TÉCNICA DE PRODUCTO (N° ' . ($index + 1) . ')', $titleFont, $centerFormat);
    
    // Name
    $table->addRow();
    $table->addCell(3000, $labelStyle)->addText('NOMBRE DEL ÍTEM', ['bold' => true]);
    $table->addCell(6000)->addText(htmlspecialchars($ficha['NOMBRE_ITEM']));
    
    // UNSPSC
    $table->addRow();
    $table->addCell(3000, $labelStyle)->addText('CÓDIGO UNSPSC', ['bold' => true]);
    $table->addCell(6000)->addText(htmlspecialchars($ficha['CODIGO_UNSPSC_FK'] ?: 'SIN_ASIGNAR'));
    
    // Denominacion Section
    $table->addRow();
    $table->addCell(9000, ['gridSpan' => 2, 'bgColor' => 'D8D8D8', 'valign' => VerticalJc::CENTER])
          ->addText('DENOMINACIÓN TÉCNICA DEL BIEN', ['bold' => true], $centerFormat);
    
    $table->addRow();
    $table->addCell(9000, ['gridSpan' => 2])->addText(htmlspecialchars($ficha['DENOMINACION_TECNICA_BIEN']), null, $centerFormat);
    
    // Unidad Section
    $table->addRow();
    $table->addCell(9000, ['gridSpan' => 2, 'bgColor' => 'D8D8D8', 'valign' => VerticalJc::CENTER])
          ->addText('UNIDAD DE MEDIDA', ['bold' => true], $centerFormat);
    
    $table->addRow();
    $table->addCell(9000, ['gridSpan' => 2])->addText(htmlspecialchars($ficha['UNIDAD_MEDIDA']), null, $centerFormat);
    
    // Descripcion Section
    $table->addRow();
    $table->addCell(9000, ['gridSpan' => 2, 'bgColor' => 'D8D8D8', 'valign' => VerticalJc::CENTER])
          ->addText('DESCRIPCIÓN GENERAL', ['bold' => true], $centerFormat);
    
    $table->addRow();
    $descCell = $table->addCell(9000, ['gridSpan' => 2]);
    
    $lines = explode("\n", $ficha['DESCRIPCION_GENERAL']);
    foreach ($lines as $line) {
        $descCell->addText(htmlspecialchars(trim($line)));
    }
    
    if (!empty($ficha['IMAGEN'])) {
        $imgPath = __DIR__ . '/../' . $ficha['IMAGEN'];
        if (file_exists($imgPath)) {
            if (strtolower(pathinfo($imgPath, PATHINFO_EXTENSION)) === 'webp') {
                $imgPath = webpToPngTemp($imgPath);
            }
            if ($imgPath) {
                $descCell->addTextBreak();
                $descCell->addImage($imgPath, [
                    'height' => 120,
                    'alignment' => Jc::CENTER
                ]);
                $descCell->addText('Imagen de referencia', ['italic' => true, 'size' => 8, 'color' => '666666'], $centerFormat);
            }
        }
    }
    
    // Comentarios
    $table->addRow();
    $table->addCell(9000, ['gridSpan' => 2, 'bgColor' => 'D8D8D8', 'valign' => VerticalJc::CENTER])
          ->addText('COMENTARIOS / ESPECIFICACIONES ADICIONALES', ['bold' => true], $centerFormat);
    
    $table->addRow();
    $comentariosCell = $table->addCell(9000, ['gridSpan' => 2]);
    
    $comLines = explode("\n", $ficha['COMENTARIOS'] ?: 'Sin comentarios adicionales.');
    foreach ($comLines as $line) {
        $comentariosCell->addText(htmlspecialchars(trim($line)));
    }
    
    // Firma / Marca
    $table->addRow();
    $table->addCell(3000, $labelStyle)->addText('MARCA OFRECIDA', ['bold' => true]);
    $table->addCell(6000)->addText('N/A');
    
    $table->addRow();
    $table->addCell(3000, $labelStyle)->addText('FIRMA DEL PROPONENTE', ['bold' => true]);
    $table->addCell(6000)->addText('N/A');
}

$filename = "Fichas_Lote_" . $idLote . "_" . date('Ymd_His') . ".docx";
header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

$objWriter = IOFactory::createWriter($phpWord, 'Word2007');
$objWriter->save('php://output');

foreach ($tempImages as $tempImage) {
    @unlink($tempImage);
}
exit;
