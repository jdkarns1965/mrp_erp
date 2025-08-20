<?php
/**
 * Simple PDF generator for MRP Manual
 */

// Read the markdown file
$markdown = file_get_contents('MRP_SYSTEM_MANUAL.md');

// Basic markdown to HTML conversion
$html = $markdown;

// Convert headers
$html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
$html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
$html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
$html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);

// Convert bold and italic
$html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
$html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

// Convert code blocks
$html = preg_replace('/```([^`]+)```/s', '<pre><code>$1</code></pre>', $html);
$html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);

// Convert horizontal rules
$html = str_replace('---', '<hr>', $html);

// Convert line breaks
$html = nl2br($html);

// Simple table conversion - convert markdown tables to HTML
$lines = explode("\n", $html);
$newLines = [];
$inTable = false;

foreach ($lines as $line) {
    if (strpos($line, '|') !== false && trim($line) !== '') {
        if (!$inTable) {
            $newLines[] = '<table>';
            $inTable = true;
        }
        
        // Skip separator lines
        if (strpos($line, '---') !== false) {
            continue;
        }
        
        $cells = explode('|', trim($line, '|'));
        $row = '<tr>';
        $isHeader = false;
        
        foreach ($cells as $cell) {
            $cell = trim($cell);
            if (!empty($cell)) {
                if (strpos($cell, '**') !== false) {
                    $cell = str_replace(['**'], '', $cell);
                    $row .= '<th>' . $cell . '</th>';
                    $isHeader = true;
                } else {
                    $row .= '<td>' . $cell . '</td>';
                }
            }
        }
        $row .= '</tr>';
        $newLines[] = $row;
    } else {
        if ($inTable) {
            $newLines[] = '</table>';
            $inTable = false;
        }
        $newLines[] = $line;
    }
}

if ($inTable) {
    $newLines[] = '</table>';
}

$html = implode("\n", $newLines);

// Add CSS and HTML structure
$fullHtml = '<!DOCTYPE html>
<html>
<head>
    <title>MRP System Manual</title>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: "Arial", sans-serif; 
            margin: 40px; 
            line-height: 1.6; 
            color: #333;
            max-width: 1200px;
        }
        h1 { 
            color: #2c3e50; 
            border-bottom: 3px solid #3498db; 
            padding-bottom: 10px; 
            margin-top: 40px;
            page-break-before: auto;
        }
        h2 { 
            color: #34495e; 
            margin-top: 30px;
            border-left: 4px solid #3498db;
            padding-left: 15px;
        }
        h3 { 
            color: #7f8c8d; 
            margin-top: 20px;
        }
        h4 {
            color: #95a5a6;
            margin-top: 15px;
        }
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin: 20px 0;
            border: 1px solid #ddd;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 12px 8px; 
            text-align: left;
            vertical-align: top;
        }
        th { 
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        code { 
            background-color: #f4f4f4; 
            padding: 2px 6px; 
            border-radius: 3px;
            font-family: "Courier New", monospace;
            font-size: 0.9em;
        }
        pre { 
            background-color: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            overflow-x: auto;
            border-left: 4px solid #3498db;
        }
        pre code {
            background: none;
            padding: 0;
        }
        hr {
            border: none;
            border-top: 2px solid #ecf0f1;
            margin: 30px 0;
        }
        .page-break {
            page-break-before: always;
        }
        @media print { 
            body { 
                margin: 20px; 
                font-size: 11px;
            }
            h1 { 
                page-break-before: always;
                font-size: 18px;
            }
            h2 {
                page-break-after: avoid;
                font-size: 16px;
            }
            h3 {
                page-break-after: avoid;
                font-size: 14px;
            }
            table {
                page-break-inside: avoid;
            }
            pre {
                page-break-inside: avoid;
            }
        }
        .toc {
            background-color: #ecf0f1;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid #3498db;
        }
        .cover-page {
            text-align: center;
            padding: 100px 0;
            page-break-after: always;
        }
        .cover-title {
            font-size: 36px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .cover-subtitle {
            font-size: 24px;
            color: #7f8c8d;
            margin-bottom: 40px;
        }
        .cover-info {
            font-size: 16px;
            color: #95a5a6;
        }
    </style>
</head>
<body>
<div class="cover-page">
    <h1 class="cover-title">MRP System Manual</h1>
    <h2 class="cover-subtitle">Complete Feature Guide for Basic and Enhanced MRP</h2>
    <div class="cover-info">
        <p><strong>Version:</strong> 1.0</p>
        <p><strong>Created:</strong> January 2025</p>
        <p><strong>System:</strong> MRP/ERP Manufacturing System</p>
    </div>
</div>

' . $html . '

<hr>
<div style="text-align: center; color: #7f8c8d; margin-top: 40px;">
    <p><em>End of MRP System Manual</em></p>
    <p>Generated on ' . date('Y-m-d H:i:s') . '</p>
</div>

</body>
</html>';

// Save the HTML file
file_put_contents('MRP_SYSTEM_MANUAL.html', $fullHtml);

echo "✅ PDF-ready HTML created: MRP_SYSTEM_MANUAL.html\n";
echo "📖 Open this file in a web browser and use 'Print to PDF' to generate the PDF\n";
echo "🔗 File location: /var/www/html/mrp_erp/MRP_SYSTEM_MANUAL.html\n";
echo "🌐 Browser URL: http://localhost/mrp_erp/MRP_SYSTEM_MANUAL.html\n";