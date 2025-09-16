<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/recipes.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id = (int)($_GET['id'] ?? 0);
$recipe = get_recipe_by_id($id);
if (!$recipe) {
    http_response_code(404);
    echo 'Rezept nicht gefunden';
    exit;
}

$title = (string)($recipe['title'] ?? 'Rezept');
$author = (string)($recipe['author_name'] ?? '');
$createdAt = isset($recipe['created_at']) ? date('d.m.Y H:i', strtotime((string)$recipe['created_at'])) : '';

// Include images? Default yes; allow ?images=0|false|no to disable
$includeImages = true;
if (isset($_GET['images'])) {
    $val = strtolower((string)$_GET['images']);
    $includeImages = !in_array($val, ['0','false','no','nein'], true);
}

// Build minimal, print-friendly HTML
$imagesHtml = '';
if ($includeImages && !empty($recipe['images'])) {
    foreach ($recipe['images'] as $img) {
        $path = ltrim((string)$img['file_path'], '/');
        $imagesHtml .= '<div class="photo"><img src="' . htmlspecialchars($path) . '" /></div>';
    }
}

$ingredientsHtml = '';
foreach (($recipe['ingredients'] ?? []) as $ingredient) {
    $qty = isset($ingredient['quantity']) && $ingredient['quantity'] !== null && $ingredient['quantity'] !== '' ? (string)$ingredient['quantity'] : '';
    $unit = trim((string)($ingredient['unit'] ?? ''));
    $name = trim((string)($ingredient['name'] ?? ''));
    $parts = array_filter([$qty, $unit, $name], fn($v) => $v !== '');
    if (!empty($parts)) {
        $ingredientsHtml .= '<li>' . htmlspecialchars(implode(' ', $parts)) . '</li>';
    }
}

$stepsHtml = '';
foreach (($recipe['steps'] ?? []) as $idx => $step) {
    $stepsHtml .= '<li>' . nl2br(htmlspecialchars((string)($step['description'] ?? ''))) . '</li>';
}

$difficulty = (string)($recipe['difficulty'] ?? '');
if ($difficulty === 'easy') { $difficulty = 'Leicht'; }
elseif ($difficulty === 'medium') { $difficulty = 'Mittel'; }
elseif ($difficulty === 'hard') { $difficulty = 'Schwer'; }

$metaLines = [];
if ($difficulty !== '') { $metaLines[] = 'Schwierigkeit: ' . htmlspecialchars($difficulty); }
if (!empty($recipe['duration_minutes'])) { $metaLines[] = 'Dauer: ' . (int)$recipe['duration_minutes'] . ' Min'; }
if (!empty($recipe['portions'])) { $metaLines[] = 'Portionen: ' . (int)$recipe['portions']; }
if (!empty($recipe['category'])) { $metaLines[] = 'Kategorie: ' . htmlspecialchars((string)$recipe['category']); }
$meta = implode(' · ', $metaLines);

// Branding: only logo (no app name)
$brandingHtml = '';
$logoRel = ltrim((string)APP_LOGO_PATH, '/');
$logoAbs = __DIR__ . '/' . $logoRel;
if (is_file($logoAbs)) {
    $brandingHtml = '<div class="brand"><img src="' . htmlspecialchars($logoRel) . '" alt="" /></div>';
} else {
    $brandingHtml = '';
}

$html = '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>' . htmlspecialchars($title) . ' - PDF</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 12px; }
        .brand { text-align: center; margin: 0 0 12px 0; width: 100%; }
        .brand img { height: 35px; width: auto; display: block; margin: 0 auto; }
        h1 { font-size: 20px; margin: 0 0 6px 0; }
        .meta { color: #555; font-size: 11px; margin-bottom: 15px; }
        .section { margin-top: 14px; }
        .section h2 { font-size: 14px; margin: 0 0 8px 0; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
        .description { white-space: pre-wrap; line-height: 1.5; }
        ul { margin: 0; padding-left: 18px; }
        ol { margin: 0; padding-left: 18px; }
        li { margin: 4px 0; }
        .photos { margin: 8px 0 12px 0; max-width: 100%;  display: inline-flex; flex-wrap: wrap; gap: 10px; padding-top:55px;  }
        .photo { margin: 6px 0; max-width: 100%;  display: inline-flex; }
        .photo img { width: 150px; height: auto; object-fit: cover; margin: 5px; border: 1px solid #ddd; }
        .footer { margin-top: 16px; color: #777; font-size: 10px; }
    </style>
    <!-- Dompdf chroot is set to the public directory; images use relative paths like "uploads/..." -->
    </head>
<body>
    ' . $brandingHtml . '
    <h1>' . htmlspecialchars($title) . '</h1>
    <div class="meta">von ' . htmlspecialchars($author) . ' · ' . htmlspecialchars($createdAt) . '</div>
    ' . ($meta ? '<div class="meta">' . $meta . '</div>' : '') . '
    ' . ($imagesHtml !== '' ? '<div class="photos">' . $imagesHtml . '</div>' : '') . '
    <div class="section">
        <h2>Beschreibung</h2>
        <div class="description">' . nl2br(htmlspecialchars((string)($recipe['description'] ?? ''))) . '</div>
    </div>
    <div class="section">
        <h2>Zutaten</h2>
        <ul>' . $ingredientsHtml . '</ul>
    </div>
    <div class="section">
        <h2>Zubereitung</h2>
        <ol>' . $stepsHtml . '</ol>
    </div>
</body>
</html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->setChroot(__DIR__); // public directory; allows relative paths like uploads/...

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title);
$filename = ($slug !== '' ? $slug : 'rezept') . '.pdf';

$dompdf->stream($filename, [ 'Attachment' => true ]);
exit;


