<?php
require_once 'config/database.php';
requireLogin();

$id         = (int)($_GET['id'] ?? 0);
$modeStruk  = $_GET['mode'] ?? '';
$hutangInfo = $_SESSION['hutang_info'] ?? null;

$isHutang = ($modeStruk === 'hutang' && $hutangInfo);

if ($isHutang) {
    unset($_SESSION['hutang_info']);

    $trx   = null;
    $items = array_map(function ($it) {
        return [
            'nama'     => $it['nama'],
            'qty'      => $it['qty'],
            'subtotal' => $it['subtotal'],
        ];
    }, $hutangInfo['items']);

    $pageTitle    = 'Struk Hutang';
    $pageSubtitle = 'Bukti transaksi hutang';
} else {
    $stmt = $pdo->prepare("
        SELECT t.*, u.nama AS kasir
        FROM transaksi t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.id = ? LIMIT 1
    ");
    $stmt->execute([$id]);
    $trx = $stmt->fetch();

    if (!$trx) {
        flash('error', 'Transaksi tidak ditemukan.');
        redirect('transaksi.php');
    }

    $det = $pdo->prepare("
        SELECT d.*, p.nama
        FROM transaksi_detail d
        LEFT JOIN produk p ON p.id = d.produk_id
        WHERE d.transaksi_id = ?
    ");
    $det->execute([$id]);
    $items = $det->fetchAll();

    $pageTitle    = 'Struk Transaksi';
    $pageSubtitle = 'Bukti transaksi ' . $trx['kode'];
}

require_once 'includes/header.php';
?>

<div class="struk-wrapper">
  <div class="struk" id="struk">

    <div class="struk-head">
      <h2>KASIR BMT</h2>
      <div class="struk-line"></div>
      <strong>Baitul Maal wat Tamwil</strong>
      <p>
        Jl. Bukit Duri Selatan No.29, Bukit Duri,<br>
        Tebet, Jakarta Selatan 12840
      </p>
      <p class="struk-telp">Telp. 021-8319994</p>
      <div class="struk-line"></div>
    </div>

    <?php if ($isHutang): ?>
    <div class="hutang-banner">
      <div class="label">⚠️ Transaksi Hutang</div>
      <div class="nama"><?= e($hutangInfo['nama']) ?></div>
      <div class="sisa-label">Sisa Hutang</div>
      <div class="sisa"><?= rupiah($hutangInfo['sisa']) ?></div>
    </div>
    <?php endif; ?>

    <div class="struk-meta">
      <?php if ($isHutang): ?>
        <?php if (!empty($hutangInfo['trx_id'])): ?>
        <div>
          <span>No. Transaksi</span>
          <strong>DP-<?= (int)$hutangInfo['trx_id'] ?></strong>
        </div>
        <?php else: ?>
        <div>
          <span>No. Transaksi</span>
          <strong>HUTANG-<?= date('YmdHis') ?></strong>
        </div>
        <?php endif; ?>
        <div>
          <span>Tanggal</span>
          <strong><?= tanggalIndo($hutangInfo['tanggal']) ?></strong>
        </div>
        <div>
          <span>Kasir</span>
          <strong><?= e($hutangInfo['kasir']) ?></strong>
        </div>
        <?php if (!empty($hutangInfo['keterangan'])): ?>
        <div>
          <span>Keterangan</span>
          <strong><?= e($hutangInfo['keterangan']) ?></strong>
        </div>
        <?php endif; ?>
      <?php else: ?>
        <div>
          <span>No. Transaksi</span>
          <strong><?= e($trx['kode']) ?></strong>
        </div>
        <div>
          <span>Tanggal</span>
          <strong><?= tanggalIndo($trx['tanggal']) ?></strong>
        </div>
        <div>
          <span>Kasir</span>
          <strong><?= e($trx['kasir'] ?? '-') ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <table class="struk-table">
      <thead>
        <tr>
          <th>Item</th>
          <th>Qty</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= e($it['nama'] ?? '-') ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td><?= rupiah($it['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="struk-total">
      <?php if ($isHutang): ?>
        <div class="grand">
          <span>TOTAL</span>
          <span><?= rupiah($hutangInfo['total']) ?></span>
        </div>
        <div>
          <span>DP / Dibayar</span>
          <span><?= rupiah($hutangInfo['dibayar']) ?></span>
        </div>
        <div class="sisa-row">
          <span>SISA HUTANG</span>
          <span><?= rupiah($hutangInfo['sisa']) ?></span>
        </div>
      <?php else: ?>
        <div class="grand">
          <span>TOTAL</span>
          <span><?= rupiah($trx['total']) ?></span>
        </div>
        <div>
          <span>Bayar</span>
          <span><?= rupiah($trx['bayar']) ?></span>
        </div>
        <div>
          <span>Kembalian</span>
          <span><?= rupiah($trx['kembalian']) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <div class="struk-foot">
      <?php if ($isHutang): ?>
        <strong>Hutang Tercatat</strong>
        Mohon lunasi sisa hutang.<br>
        <em>Simpan struk ini sebagai bukti.</em>
      <?php else: ?>
        <strong>Terima Kasih!</strong>
        Telah berbelanja di Kasir BMT.<br>
        <em>Barang yang sudah dibeli tidak dapat ditukar.</em>
      <?php endif; ?>
    </div>

  </div>

  <div class="struk-actions no-print">
    <button class="btn btn-primary" onclick="window.print()">
      🖨️ Cetak Struk
    </button>

    <a href="kasir.php" class="btn btn-outline">
      ➕ Transaksi Baru
    </a>

    <a href="transaksi.php" class="btn btn-outline">
      📋 Daftar Transaksi
    </a>

    <?php if ($isHutang): ?>
    <a href="hutang.php" class="btn btn-outline">
      📝 Daftar Hutang
    </a>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>