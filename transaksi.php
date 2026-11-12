<?php
require_once 'config/database.php';
requireLogin();

/* ============================================================
   FILTER PERIODE
   ============================================================ */
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-d');

foreach (['dari' => &$dari, 'sampai' => &$sampai] as &$v) {
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        $v = date('Y-m-d');
    }
}
unset($v, $d);

if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}

/* ============================================================
   DAFTAR TRANSAKSI
   ============================================================ */
$stmt = $pdo->prepare("
    SELECT t.*, u.nama AS kasir,
           (SELECT COUNT(*) FROM transaksi_detail WHERE transaksi_id = t.id) AS jml_item
    FROM transaksi t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.jenis = 'penjualan' AND DATE(t.tanggal) BETWEEN ? AND ?
    ORDER BY t.id DESC
");
$stmt->execute([$dari, $sampai]);
$rows = $stmt->fetchAll();

$totalOmzet    = array_sum(array_column($rows, 'total'));
$totalTransaksi = count($rows);
$totalItem      = array_sum(array_column($rows, 'jml_item'));
$rataTransaksi  = $totalTransaksi > 0 ? $totalOmzet / $totalTransaksi : 0;

/* ============================================================
   DETAIL PER TRANSAKSI (untuk expand)
   ============================================================ */
$detailByTransaksi = [];
if ($rows) {
    $trxIds = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($trxIds), '?'));

    $stmt = $pdo->prepare("
        SELECT d.*, p.nama AS nama_produk, p.kode AS kode_produk, p.satuan
        FROM transaksi_detail d
        JOIN produk p ON p.id = d.produk_id
        WHERE d.transaksi_id IN ($placeholders)
        ORDER BY d.id ASC
    ");
    $stmt->execute($trxIds);
    foreach ($stmt->fetchAll() as $row) {
        $detailByTransaksi[$row['transaksi_id']][] = $row;
    }
}

$pageTitle    = 'Riwayat Transaksi';
$pageSubtitle = 'Daftar transaksi penjualan';
require_once 'includes/header.php';
?>

<!-- ===== FILTER ===== -->
<div class="card filter-card">
  <div class="card-head">
    <h2>🔎 Filter Periode</h2>
    <span class="tag tag-green">Omzet: <?= rupiah($totalOmzet) ?></span>
  </div>
  <div class="card-body">
    <form method="get" class="filter-form">
      <div class="form-group">
        <label>Dari Tanggal</label>
        <input type="date" name="dari" value="<?= e($dari) ?>">
      </div>
      <div class="form-group">
        <label>Sampai Tanggal</label>
        <input type="date" name="sampai" value="<?= e($sampai) ?>">
      </div>
      <button class="btn btn-primary">🔍 Tampilkan</button>
      <a href="transaksi.php" class="btn btn-outline">Reset</a>
      <a href="export_transaksi.php?dari=<?= e($dari) ?>&sampai=<?= e($sampai) ?>"
         class="btn btn-export-xls">📥 Excel</a>
    </form>
  </div>
</div>

<!-- ===== STATS ===== -->
<div class="hutang-stats">
  <div class="stat">
    <div class="stat-label">Total Omzet</div>
    <div class="stat-value"><?= rupiah($totalOmzet) ?></div>
    <div class="stat-sub">periode terpilih</div>
  </div>
  <div class="stat">
    <div class="stat-label">Jumlah Transaksi</div>
    <div class="stat-value"><?= number_format($totalTransaksi) ?></div>
    <div class="stat-sub"><?= date('d/m/Y', strtotime($dari)) ?> – <?= date('d/m/Y', strtotime($sampai)) ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Total Item Terjual</div>
    <div class="stat-value"><?= number_format($totalItem) ?></div>
    <div class="stat-sub">unit produk</div>
  </div>
  <div class="stat">
    <div class="stat-label">Rata-rata / Transaksi</div>
    <div class="stat-value"><?= rupiah($rataTransaksi) ?></div>
    <div class="stat-sub">nilai belanja rata-rata</div>
  </div>
</div>

<!-- ===== DAFTAR TRANSAKSI ===== -->
<div class="card">
  <div class="card-head">
    <h2>🧾 Daftar Transaksi (<?= count($rows) ?>)</h2>
  </div>
  <div class="card-body">
    <p class="text-muted" style="margin-bottom:14px; font-size:13px;">
      ℹ️ Klik baris transaksi untuk melihat detail item yang dibeli.
    </p>

    <?php if (!$rows): ?>
      <div class="empty-state">
        <span class="emoji">📭</span>
        Tidak ada transaksi pada periode ini.
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:30px"></th>
              <th>Kode</th>
              <th>Tanggal</th>
              <th>Kasir</th>
              <th class="text-center">Item</th>
              <th class="text-right">Total</th>
              <th class="text-right">Bayar</th>
              <th class="text-right">Kembali</th>
              <th class="text-center" style="width:80px">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r):
            $details = $detailByTransaksi[$r['id']] ?? [];
            $rowId   = 'trx-' . (int)$r['id'];
          ?>
            <tr class="row-transaksi" data-target="<?= $rowId ?>">
              <td class="text-center">
                <span class="toggle-icon" id="icon-<?= $rowId ?>">▶</span>
              </td>
              <td><span class="tag tag-green"><?= e($r['kode']) ?></span></td>
              <td class="text-muted"><?= tanggalIndo($r['tanggal']) ?></td>
              <td class="text-muted"><?= e($r['kasir'] ?? '-') ?></td>
              <td class="text-center"><?= (int)$r['jml_item'] ?></td>
              <td class="text-right strong"><?= rupiah($r['total']) ?></td>
              <td class="text-right"><?= rupiah($r['bayar']) ?></td>
              <td class="text-right"><?= rupiah($r['kembalian']) ?></td>
              <td class="text-center" style="white-space:nowrap" onclick="event.stopPropagation()">
                <a href="struk.php?id=<?= (int)$r['id'] ?>"
                   class="btn btn-outline btn-sm"
                   title="Lihat Struk">🧾</a>
              </td>
            </tr>

            <tr class="row-detail" id="<?= $rowId ?>">
              <td colspan="9">
                <div class="detail-inner" id="inner-<?= $rowId ?>">
                  <div class="detail-content">
                    <div class="detail-table-wrap">
                      <table style="margin:0">
                        <thead>
                          <tr>
                            <th>Kode</th>
                            <th>Produk</th>
                            <th class="text-center">Qty</th>
                            <th class="text-right">Harga</th>
                            <th class="text-right">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                        <?php if (!$details): ?>
                          <tr><td colspan="5">
                            <div class="empty-state">Tidak ada detail.</div>
                          </td></tr>
                        <?php else: foreach ($details as $d): ?>
                          <tr>
                            <td><span class="tag tag-gray"><?= e($d['kode_produk']) ?></span></td>
                            <td><?= e($d['nama_produk']) ?></td>
                            <td class="text-center"><?= (int)$d['qty'] ?> <?= e($d['satuan']) ?></td>
                            <td class="text-right"><?= rupiah($d['harga']) ?></td>
                            <td class="text-right strong"><?= rupiah($d['subtotal']) ?></td>
                          </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                        <?php if ($details): ?>
                        <tfoot>
                          <tr>
                            <td colspan="4" class="text-right">Total Transaksi</td>
                            <td class="text-right" style="color:var(--g700, #047857);">
                              <?= rupiah($r['total']) ?>
                            </td>
                          </tr>
                        </tfoot>
                        <?php endif; ?>
                      </table>
                    </div>

                    <?php if (!empty($r['keterangan'])): ?>
                      <div class="trx-note">
                        📝 <strong>Keterangan:</strong> <?= e($r['keterangan']) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>