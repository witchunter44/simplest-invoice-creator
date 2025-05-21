<?php
require 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;

    function numberToWords($number) {
        $units = ['', 'jeden', 'dwa', 'trzy', 'cztery', 'pięć', 'sześć', 'siedem', 'osiem', 'dziewięć'];
        $teens = ['dziesięć', 'jedenaście', 'dwanaście', 'trzynaście', 'czternaście', 'piętnaście', 'szesnaście', 'siedemnaście', 'osiemnaście', 'dziewiętnaście'];
        $tens = ['', 'dziesięć', 'dwadzieścia', 'trzydzieści', 'czterdzieści', 'pięćdziesiąt', 'sześćdziesiąt', 'siedemdziesiąt', 'osiemdziesiąt', 'dziewięćdziesiąt'];
        $hundreds = ['', 'sto', 'dwieście', 'trzysta', 'czterysta', 'pięćset', 'sześćset', 'siedemset', 'osiemset', 'dziewięćset'];

        $thousands = ['', 'tysiąc', 'tysiące', 'tysięcy'];
        $millions = ['', 'milion', 'miliony', 'milionów'];

        if ($number == 0) {
            return 'zero';
        }

        if ($number < 0) {
            return 'minus ' . numberToWords(-$number);
        }

        $result = '';

        if ($number >= 1000000) {
            $millions_count = intval($number / 1000000);
            $number %= 1000000;
            $result .= numberToWords($millions_count) . ' ' . getWordForm($millions_count, $millions) . ' ';
        }

        if ($number >= 1000) {
            $thousands_count = intval($number / 1000);
            $number %= 1000;
            $result .= numberToWords($thousands_count) . ' ' . getWordForm($thousands_count, $thousands) . ' ';
        }

        if ($number >= 100) {
            $result .= $hundreds[intval($number / 100)] . ' ';
            $number %= 100;
        }

        if ($number >= 20) {
            $result .= $tens[intval($number / 10)] . ' ';
            $number %= 10;
        }

        if ($number >= 10) {
            $result .= $teens[$number - 10] . ' ';
        } else if ($number > 0) {
            $result .= $units[$number] . ' ';
        }

        return trim($result);
    }

    function getWordForm($number, $forms) {
        $number = abs($number);
        if ($number == 1) {
            return $forms[1];
        } elseif ($number >= 2 && $number <= 4) {
            return $forms[2];
        } else {
            return $forms[3];
        }
    }

    function formatPrice($number) {
        $zlote = floor($number);
        $grosze = round(($number - $zlote) * 100);

        $zloteWords = numberToWords($zlote) . ' złotych';
        if ($grosze > 0) {
            $groszeWords = numberToWords($grosze) . ' groszy';
            return $zloteWords . ' i ' . $groszeWords;
        } else {
            return $zloteWords;
        }
    }

    // create invoice
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="pl">
    <head>
        <meta charset="UTF-8">
        <title>Faktura</title>
        <style>
            * {
                font-family: arial;
                font-size: 8px;
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                margin: 0;
                padding: 0;
            }

            .container {
                width: 100%;
                padding: 15px;
            }

            .header {
                padding: 15px;
                max-width: 50%;
                display: inline-block;
            }

            .details, .summary {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }

            .details th, .details td, .summary th, .summary td {
                border: 1px solid #000;
                padding: 5px;
            }

            .summary tr:nth-child(even) {
                background-color: #f2f2f2;
            }

            .footer {
                text-align: right;
                position: absolute;
                bottom: 0;
                width: 100%;
            }
            .hinvoice {
                font-size: 12px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <p class="invoice-date">Wystawiono dnia: <?= htmlspecialchars($data['issue_date']) ?>, <?= htmlspecialchars($data['place']) ?></p>
            <table class="header">
                <tr style="font-size: 12pt;">
                    <th>
                    <?php
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
                        $logoPath = 'uploads/' . basename($_FILES['logo']['name']);
                        move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath);
                        echo '<img src="' . htmlspecialchars($logoPath) . '" alt="Logo" width="100">';
                    }
                    ?> 
                    </th>
                    <th>
                    <h1>Faktura VAT nr <?= htmlspecialchars($data['invoice_number']) ?></h1>
                    <p>Data sprzedaży: <?= htmlspecialchars($data['sale_date']) ?></p>
                    <p>Sposób zapłaty: <?= htmlspecialchars($data['payment_method']) ?></p>
                    <p>Termin płatności: <?= htmlspecialchars($data['payment_due_date']) ?></p>
                    </th>  
                </tr>     
            </table>
            <table class="details">
                <tr>
                    <th>Sprzedawca</th>
                    <th>Nabywca</th>
                    <th>Odbiorca</th>
                </tr>
                <tr>
                    <td><?= nl2br(htmlspecialchars($data['seller'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($data['buyer'])) ?></td>
                    <td><?= nl2br(htmlspecialchars($data['recipient'])) ?></td>
                </tr>
            </table>
            <div class="details">
                <h2>POZYCJE FAKTURY</h2>      
            </div>
            <table class="details">
                <tr>
                    <th>LP</th>
                    <th>Nazwa towaru/usługi</th>
                    <th>Rabat</th>
                    <th>Ilość</th>
                    <th>Cena netto</th>
                    <th>Wartość netto</th>
                    <th>VAT</th>
                    <th>Wartość brutto</th>
                </tr>
                <?php
                $totalNet = 0;
                $totalVat = 0;
                $totalGross = 0;

                if (isset($data['item_name']) && isset($data['item_quantity']) && isset($data['item_price']) && isset($data['item_discount'])) {
                    foreach ($data['item_name'] as $index => $name) {
                        $quantity = $data['item_quantity'][$index];
                        $price = $data['item_price'][$index];
                        $discount = $data['item_discount'][$index];
                        $netValue = $quantity * $price * (1 - $discount / 100);
                        $grossValue = $netValue * 1.23;
                        $totalNet += $netValue;
                        $totalVat += $netValue * 0.23;
                        $totalGross += $grossValue;
                        ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($name) ?></td>
                            <td><?= htmlspecialchars($discount) ?>%</td>
                            <td><?= htmlspecialchars($quantity) ?></td>
                            <td><?= htmlspecialchars($price) ?> PLN</td>
                            <td><?= number_format($netValue, 2) ?> PLN</td>
                            <td>23%</td>
                            <td><?= number_format($grossValue, 2) ?> PLN</td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </table>
            <div class="details">
                <h2>PODSUMOWANIE</h2>      
            </div>
            <table class="summary">
                <tr>
                    <th></th>
                    <th>Wartość netto</th>
                    <th>Stawka VAT</th>
                    <th>VAT</th>
                    <th>Wartość brutto</th>
                </tr>
                <tr>
                    <td>Razem: </td>
                    <td><?= number_format($totalNet, 2) ?> PLN</td>
                    <td>23%</td>
                    <td><?= number_format($totalVat, 2) ?> PLN</td>
                    <td><?= number_format($totalGross, 2) ?> PLN</td>
                </tr>
                <tr>
                    <td colspan="4">Zapłacono: </td>
                    <td>0.00 PLN</td>
                </tr>
                <tr>
                    <td colspan="4">Pozostało do zapłaty: </td>
                    <td><?= number_format($totalGross, 2) ?> PLN</td>
                </tr>
                <tr>
                    <td colspan="4">Słownie: </td>
                    <td><?= formatPrice($totalGross) ?></td>
                </tr>
                <tr>
                    <td colspan="4">Konto bankowe: </td>
                    <td><?= nl2br(htmlspecialchars($data['account_number'])) ?></td>
                </tr>
                <tr>
                    <td colspan="4">Uwagi: </td>
                    <td><?= nl2br(htmlspecialchars($data['notes'])) ?></td>
                </tr>
            </table>
            <div class="footer">
                <p>Dziękujemy za zakupy!</p>
                <p>Strona 1/1</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $pdf = new \TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Twój Autor');
    $pdf->SetTitle('Faktura');
    $pdf->SetSubject('Faktura');
    $pdf->SetKeywords('TCPDF, PDF, faktura, test, polskie znaki');
    $pdf->SetMargins(PDF_MARGIN_LEFT, 5, PDF_MARGIN_RIGHT);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    $pdf->setFontSubsetting(true);
    $pdf->SetFont('dejavusans', '', 12, '', true);
    $pdf->setPrintFooter(false);
    $pdf->setPrintHeader(false);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');

    $invoiceDirectory = __DIR__ . '/faktury/';
    if (!file_exists($invoiceDirectory)) {
        mkdir($invoiceDirectory, 0777, true);
    }

    $cleanInvoiceNumber = str_replace('/', '', htmlspecialchars($data['invoice_number']));

    $invoiceFileName = $invoiceDirectory . $cleanInvoiceNumber . '.pdf';

    try {
        $pdf->Output($invoiceFileName, 'F');
        echo '<h1 style="text-align: center;">Plik PDF został pomyślnie zapisany.</h1>';
        echo '<div style="text-align: center;">';
        echo '<a href="index.html" style="display: inline-block;">';
        echo '<button style="width: 150px;">Kolejna Faktura</button>';
        echo '</a>';
        echo '</div>';
    } catch (Exception $e) {
        echo 'Wystąpił błąd podczas zapisywania pliku PDF: ' . $e->getMessage();
    }
}
?>
