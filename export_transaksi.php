<?php
require_once 'config/database.php';
require_once 'config/export_helper.php';
requireLogin();

export_validate_periode($dari, $sampai);
$format = export_format();

$stmt = $pdo->prepare("
    SELECT t.*, u.nama AS kasir
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.jenis = 'penjualan' AND DATE(t.tanggal) BETWEEN ? AND ?
    ORDER BY t.id DESC
");
$stmt->execute([$dari, $sampai]);
$rows = $stmt->fetchAll();

$totalOmzet   = array_sum(array_column($rows, 'total'));
$totalBayar   = array_sum(array_column($rows, 'bayar'));
$totalKembali = array_sum(array_column($rows, 'kembalian'));

/* ============================================================
 * OUTPUT CSV
 * ============================================================ */
if ($format === 'csv') {
    export_send_headers("Riwayat_Transaksi_{$dari}_sd_{$sampai}", 'csv');
    $fh = csv_start();

    csv_row($fh, ['RIWAYAT TRANSAKSI PENJUALAN']);
    csv_row($fh, ['Periode', date('d/m/Y', strtotime($dari)) . ' s/d ' . date('d/m/Y', strtotime($sampai))]);
    csv_row($fh, ['Jumlah Transaksi', count($rows)]);
    csv_row($fh, ['Total Omzet', $totalOmzet]);
    csv_row($fh, []);

    csv_row($fh, ['Kode', 'Tanggal', 'Kasir', 'Total', 'Bayar', 'Kembalian', 'Keterangan']);
    foreach ($rows as $r) {
        csv_row($fh, [
            $r['kode'],
            date('d/m/Y H:i', strtotime($r['tanggal'])),
            $r['kasir'] ?? '-',
            (float)$r['total'],
            (float)$r['bayar'],
            (float)$r['kembalian'],
            $r['keterangan'] ?: '-',
        ]);
    }
    csv_row($fh, ['TOTAL', '', '', $totalOmzet, $totalBayar, $totalKembali, '']);

    fclose($fh);
    exit;
}

/* ============================================================
 * OUTPUT XLS
 * ============================================================ */
export_send_headers("Riwayat_Transaksi_{$dari}_sd_{$sampai}", 'xls');
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
    <x:Name>Riwayat Transaksi</x:Name>
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
  .total-row td { background: #ecfdf5; font-weight: bold; }
  .spacer td { border: none; height: 8px; }
  .text-green { color: #047857; font-weight: bold; }
</style>
</head>
<body>

<table>
  <tr><td colspan="7" class="title">RIWAYAT TRANSAKSI PENJUALAN</td></tr>
  <tr>
    <td colspan="7" class="subtitle">
      Periode: <?= date('d/m/Y', strtotime($dari)) ?> s/d <?= date('d/m/Y', strtotime($sampai)) ?>
    </td>
  </tr>
  <tr class="spacer"><td colspan="7"></td></tr>

  <tr>
    <td colspan="7" style="text-align:right; border:none;">
      Jumlah Transaksi: <strong><?= count($rows) ?></strong>
      &nbsp;|&nbsp;
      Total Omzet: <strong class="text-green"><?= number_format($totalOmzet, 0, ',', '.') ?></strong>
    </td>
  </tr>
  <tr class="spacer"><td colspan="7"></td></tr>

  <tr>
    <th style="width:120px">Kode</th>
    <th style="width:140px">Tanggal</th>
    <th>Kasir</th>
    <th class="num">Total</th>
    <th class="num">Bayar</th>
    <th class="num">Kembalian</th>
    <th>Keterangan</th>
  </tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td class="center"><?= htmlspecialchars($r['kode']) ?></td>
    <td class="center"><?= date('d/m/Y H:i', strtotime($r['tanggal'])) ?></td>
    <td><?= htmlspecialchars($r['kasir'] ?? '-') ?></td>
    <td class="num"><?= $r['total'] ?></td>
    <td class="num"><?= $r['bayar'] ?></td>
    <td class="num"><?= $r['kembalian'] ?></td>
    <td><?= htmlspecialchars($r['keterangan'] ?: '-') ?></td>
  </tr>
  <?php endforeach; ?>

  <tr class="total-row">
    <td colspan="3">TOTAL</td>
    <td class="num"><?= $totalOmzet ?></td>
    <td class="num"><?= $totalBayar ?></td>
    <td class="num"><?= $totalKembali ?></td>
    <td></td>
  </tr>
</table>

</body>
</html>