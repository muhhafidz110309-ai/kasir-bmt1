<?php
require_once 'config/database.php';
require_once 'config/export_helper.php';
requireLogin();

export_validate_periode($dari, $sampai);
$format = export_format();

/* ===== REKAP ===== */
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS jml, COALESCE(SUM(total),0) AS omzet,
           COALESCE(AVG(total),0) AS rata
    FROM transaksi
    WHERE jenis='penjualan' AND DATE(tanggal) BETWEEN ? AND ?
");
$stmt->execute([$dari, $sampai]);
$rekapJual = $stmt->fetch();

/* ===== LABA KOTOR ===== */
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM((d.harga - p.harga_beli) * d.qty),0) AS laba
    FROM transaksi_detail d
    JOIN produk p ON p.id = d.produk_id
    JOIN transaksi t ON t.id = d.transaksi_id
    WHERE t.jenis='penjualan' AND DATE(t.tanggal) BETWEEN ? AND ?
");
$stmt->execute([$dari, $sampai]);
$labaKotor = (float)$stmt->fetchColumn();

/* ===== DETAIL PRODUK ===== */
$stmt = $pdo->prepare("
    SELECT p.id, p.kode, p.nama, p.kategori,
           SUM(d.qty) AS qty, SUM(d.subtotal) AS omzet,
           SUM((d.harga - p.harga_beli) * d.qty) AS laba
    FROM transaksi_detail d
    JOIN produk p ON p.id = d.produk_id
    JOIN transaksi t ON t.id = d.transaksi_id
    WHERE t.jenis='penjualan' AND DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY p.id, p.kode, p.nama, p.kategori
    ORDER BY omzet DESC
");
$stmt->execute([$dari, $sampai]);
$detailProduk = $stmt->fetchAll();

/* ===== HARIAN ===== */
$stmt = $pdo->prepare("
    SELECT DATE(tanggal) AS tgl, COUNT(*) AS jml, SUM(total) AS omzet
    FROM transaksi
    WHERE jenis='penjualan' AND DATE(tanggal) BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY tgl DESC
");
$stmt->execute([$dari, $sampai]);
$harian = $stmt->fetchAll();

/* ============================================================
 * OUTPUT CSV
 * ============================================================ */
if ($format === 'csv') {
    export_send_headers("Laporan_Penjualan_{$dari}_sd_{$sampai}", 'csv');
    $fh = csv_start();

    // Judul
    csv_row($fh, ['LAPORAN PENJUALAN']);
    csv_row($fh, ['Periode', date('d/m/Y', strtotime($dari)) . ' s/d ' . date('d/m/Y', strtotime($sampai))]);
    csv_row($fh, []);

    // Ringkasan
    csv_row($fh, ['RINGKASAN']);
    csv_row($fh, ['Keterangan', 'Nilai']);
    csv_row($fh, ['Total Omzet',      (float)$rekapJual['omzet']]);
    csv_row($fh, ['Total Transaksi',  (int)$rekapJual['jml']]);
    csv_row($fh, ['Rata-rata / Trx',  round($rekapJual['rata'])]);
    csv_row($fh, ['Laba Kotor',       round($labaKotor)]);
    csv_row($fh, []);

    // Harian
    csv_row($fh, ['REKAP HARIAN']);
    csv_row($fh, ['Tanggal', 'Jumlah Transaksi', 'Omzet', 'Rata-rata / Transaksi']);
    $tJml = 0; $tOmz = 0;
    foreach ($harian as $h) {
        $tJml += (int)$h['jml'];
        $tOmz += (float)$h['omzet'];
        $avg  = $h['jml'] > 0 ? $h['omzet'] / $h['jml'] : 0;
        csv_row($fh, [
            date('d/m/Y', strtotime($h['tgl'])),
            (int)$h['jml'],
            (float)$h['omzet'],
            round($avg),
        ]);
    }
    csv_row($fh, ['TOTAL', $tJml, $tOmz, $tJml ? round($tOmz / $tJml) : 0]);
    csv_row($fh, []);

    // Detail produk
    csv_row($fh, ['DETAIL PER PRODUK']);
    csv_row($fh, ['Kode', 'Produk', 'Kategori', 'Qty Terjual', 'Omzet', 'Laba']);
    $tQ = 0; $tO = 0; $tL = 0;
    foreach ($detailProduk as $d) {
        $tQ += (int)$d['qty'];
        $tO += (float)$d['omzet'];
        $tL += (float)$d['laba'];
        csv_row($fh, [
            $d['kode'],
            $d['nama'],
            $d['kategori'] ?: '-',
            (int)$d['qty'],
            (float)$d['omzet'],
            (float)$d['laba'],
        ]);
    }
    csv_row($fh, ['TOTAL', '', '', $tQ, $tO, $tL]);

    fclose($fh);
    exit;
}

/* ============================================================
 * OUTPUT XLS (HTML table)
 * ============================================================ */
export_send_headers("Laporan_Penjualan_{$dari}_sd_{$sampai}", 'xls');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta charset="UTF-8">
<!--[if gte mso 9]>
<xml>
 <x:ExcelWorkbook>
  <x:ExcelWorksheets>
   <x:ExcelWorksheet>
    <x:Name>Laporan Penjualan</x:Name>
    <x:WorksheetOptions>
     <x:DisplayGridlines/>
     <x:Print>
      <x:ValidPrinterInfo/>
      <x:PaperSizeIndex>9</x:PaperSizeIndex>
      <x:Scale>75</x:Scale>
     </x:Print>
    </x:WorksheetOptions>
   </x:ExcelWorksheet>
  </x:ExcelWorksheets>
 </x:ExcelWorkbook>
</xml>
<![endif]-->
<style>
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
  table { border-collapse: collapse; }
  th, td { border: 1px solid #7c7c7c; padding: 5px 8px; vertical-align: middle; }
  th { background: #10b981; color: #ffffff; font-weight: bold; text-align: center; font-size: 11px; }
  .num { text-align: right; mso-number-format: "#,##0"; }
  .center { text-align: center; }
  .title { font-size: 16px; font-weight: bold; text-align: center; background: #065f46; color: #ffffff; padding: 10px; border: 1px solid #065f46; }
  .subtitle { text-align: center; font-size: 11px; background: #d1fae5; border: 1px solid #7c7c7c; }
  .section { font-size: 12px; font-weight: bold; background: #d1fae5; color: #065f46; padding: 6px 8px; border: 1px solid #7c7c7c; }
  .total-row td { background: #ecfdf5; font-weight: bold; }
  .spacer td { border: none; height: 8px; }
  .text-green { color: #047857; font-weight: bold; }
</style>
</head>
<body>

<table>
  <!-- HEADER -->
  <tr><td colspan="6" class="title">LAPORAN PENJUALAN</td></tr>
  <tr>
    <td colspan="6" class="subtitle">
      Periode: <?= date('d/m/Y', strtotime($dari)) ?> s/d <?= date('d/m/Y', strtotime($sampai)) ?>
    </td>
  </tr>
  <tr class="spacer"><td colspan="6"></td></tr>

  <!-- RINGKASAN -->
  <tr><td colspan="6" class="section">RINGKASAN</td></tr>
  <tr>
    <th style="width:220px">Keterangan</th>
    <th colspan="5">Nilai</th>
  </tr>
  <tr><td>Total Omzet</td>            <td colspan="5" class="num"><?= $rekapJual['omzet'] ?></td></tr>
  <tr><td>Total Transaksi</td>        <td colspan="5" class="center"><?= $rekapJual['jml'] ?></td></tr>
  <tr><td>Rata-rata per Transaksi</td><td colspan="5" class="num"><?= round($rekapJual['rata']) ?></td></tr>
  <tr><td>Laba Kotor</td>             <td colspan="5" class="num text-green"><?= round($labaKotor) ?></td></tr>
  <tr class="spacer"><td colspan="6"></td></tr>

  <!-- HARIAN -->
  <tr><td colspan="6" class="section">REKAP HARIAN</td></tr>
  <tr>
    <th>Tanggal</th>
    <th class="center">Jumlah Transaksi</th>
    <th class="num">Omzet</th>
    <th class="num">Rata-rata / Transaksi</th>
    <th class="num" colspan="2"></th>
  </tr>
  <?php
  $totHarianJml = 0; $totHarianOmzet = 0;
  foreach ($harian as $h):
      $totHarianJml   += (int)$h['jml'];
      $totHarianOmzet += (float)$h['omzet'];
      $avg = $h['jml'] > 0 ? $h['omzet'] / $h['jml'] : 0;
  ?>
  <tr>
    <td><?= date('d/m/Y', strtotime($h['tgl'])) ?></td>
    <td class="center"><?= $h['jml'] ?></td>
    <td class="num"><?= $h['omzet'] ?></td>
    <td class="num"><?= round($avg) ?></td>
    <td colspan="2"></td>
  </tr>
  <?php endforeach; ?>
  <tr class="total-row">
    <td>TOTAL</td>
    <td class="center"><?= $totHarianJml ?></td>
    <td class="num"><?= $totHarianOmzet ?></td>
    <td class="num"><?= $totHarianJml ? round($totHarianOmzet / $totHarianJml) : 0 ?></td>
    <td colspan="2"></td>
  </tr>
  <tr class="spacer"><td colspan="6"></td></tr>

  <!-- DETAIL PRODUK -->
  <tr><td colspan="6" class="section">DETAIL PER PRODUK</td></tr>
  <tr>
    <th>Kode</th>
    <th>Produk</th>
    <th>Kategori</th>
    <th class="center">Qty Terjual</th>
    <th class="num">Omzet</th>
    <th class="num">Laba</th>
  </tr>
  <?php
  $totQty = 0; $totOmzet = 0; $totLaba = 0;
  foreach ($detailProduk as $d):
      $totQty   += (int)$d['qty'];
      $totOmzet += (float)$d['omzet'];
      $totLaba  += (float)$d['laba'];
  ?>
  <tr>
    <td class="center"><?= htmlspecialchars($d['kode']) ?></td>
    <td><?= htmlspecialchars($d['nama']) ?></td>
    <td><?= htmlspecialchars($d['kategori'] ?: '-') ?></td>
    <td class="center"><?= $d['qty'] ?></td>
    <td class="num"><?= $d['omzet'] ?></td>
    <td class="num"><?= $d['laba'] ?></td>
  </tr>
  <?php endforeach; ?>
  <tr class="total-row">
    <td colspan="3">TOTAL</td>
    <td class="center"><?= $totQty ?></td>
    <td class="num"><?= $totOmzet ?></td>
    <td class="num"><?= $totLaba ?></td>
  </tr>
</table>

</body>
</html>