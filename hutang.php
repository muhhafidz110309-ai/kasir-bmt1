<?php
require_once 'config/database.php';
requireLogin();

/* ============================================================
   AKSI: BAYAR HUTANG KONSUMEN (LUNAS / SEBAGIAN)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'bayar') {
    $nama       = trim($_POST['nama'] ?? '');
    $nominal    = (float)($_POST['nominal'] ?? 0);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($nama === '') {
        flash('error', 'Nama konsumen tidak valid.');
        redirect('hutang.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT h.*, p.nama AS nama_produk
            FROM hutang h
            JOIN produk p ON p.id = h.produk_id
            WHERE h.nama = ? AND h.sisa > 0
            ORDER BY h.id ASC
            FOR UPDATE
        ");
        $stmt->execute([$nama]);
        $items = $stmt->fetchAll();

        if (!$items) throw new Exception('Tidak ada sisa hutang atas nama ' . $nama . '.');

        $totalSisa = 0;
        foreach ($items as $it) {
            $totalSisa += (float)$it['sisa'];
        }
        $totalSisa = round($totalSisa, 2);

        if ($totalSisa <= 0) {
            throw new Exception('Hutang ' . $nama . ' sudah lunas.');
        }

        if ($nominal <= 0 || $nominal >= $totalSisa) {
            $bayar = $totalSisa;
        } else {
            $bayar = $nominal;
        }

        $isLunas = ($bayar >= $totalSisa);

        $kode = generateKode('TRX', $pdo, 'transaksi', 'kode');
        $ket  = $isLunas
                ? 'Pelunasan hutang dari ' . $nama
                : 'Cicilan hutang dari ' . $nama;

        $pdo->prepare("
            INSERT INTO transaksi (kode, user_id, jenis, total, bayar, kembalian, keterangan, tanggal)
            VALUES (?, ?, 'penjualan', ?, ?, 0, ?, NOW())
        ")->execute([
            $kode,
            user()['id'],
            $bayar,
            $bayar,
            $ket . ($keterangan ? ' - ' . $keterangan : '')
        ]);
        $trxId = (int)$pdo->lastInsertId();

        $insDet = $pdo->prepare("
            INSERT INTO transaksi_detail (transaksi_id, produk_id, qty, harga, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");

        $sisaBayar = $bayar;

        foreach ($items as $it) {
            if ($sisaBayar <= 0) break;

            $sisaItem = (float)$it['sisa'];
            if ($sisaItem <= 0) continue;

            if ($sisaBayar >= $sisaItem) {
                $insDet->execute([$trxId, $it['produk_id'], $it['qty'], $it['harga'], $sisaItem]);
                $sisaBayar -= $sisaItem;

                $pdo->prepare("UPDATE hutang SET dibayar = harga * qty, sisa = 0 WHERE id = ?")
                    ->execute([$it['id']]);
            } else {
                $insDet->execute([$trxId, $it['produk_id'], $it['qty'], $it['harga'], $sisaBayar]);

                $newSisa    = $sisaItem - $sisaBayar;
                $newDibayar = (float)$it['dibayar'] + $sisaBayar;

                $pdo->prepare("UPDATE hutang SET dibayar = ?, sisa = ? WHERE id = ?")
                    ->execute([$newDibayar, $newSisa, $it['id']]);

                $sisaBayar = 0;
            }
        }

        $pdo->commit();

        if ($isLunas) {
            flash('success', 'Hutang ' . $nama . ' sebesar ' . rupiah($totalSisa) . ' LUNAS. Transaksi ' . $kode . ' tercatat.');
        } else {
            $sisaAkhir = $totalSisa - $bayar;
            flash('success', 'Cicilan ' . rupiah($bayar) . ' dari ' . $nama . ' tercatat. Sisa hutang: ' . rupiah($sisaAkhir) . '.');
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Gagal: ' . $e->getMessage());
    }

    redirect('hutang.php');
}

/* ============================================================
   AKSI: BAYAR 1 ITEM HUTANG
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'bayar_item') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT h.*, p.nama AS nama_produk
                FROM hutang h
                JOIN produk p ON p.id = h.produk_id
                WHERE h.id = ?
                FOR UPDATE
            ");
            $stmt->execute([$id]);
            $h = $stmt->fetch();

            if (!$h) throw new Exception('Hutang tidak ditemukan.');

            $sisaItem = (float)$h['sisa'];

            if ($sisaItem <= 0) {
                throw new Exception('Item ini sudah lunas.');
            }

            $kode = generateKode('TRX', $pdo, 'transaksi', 'kode');

            $pdo->prepare("
                INSERT INTO transaksi (kode, user_id, jenis, total, bayar, kembalian, keterangan, tanggal)
                VALUES (?, ?, 'penjualan', ?, ?, 0, ?, NOW())
            ")->execute([
                $kode,
                user()['id'],
                $sisaItem,
                $sisaItem,
                'Pelunasan hutang item: ' . $h['nama_produk'] . ' (' . $h['nama'] . ')'
            ]);
            $trxId = (int)$pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO transaksi_detail (transaksi_id, produk_id, qty, harga, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$trxId, $h['produk_id'], $h['qty'], $h['harga'], $sisaItem]);

            $pdo->prepare("UPDATE hutang SET dibayar = harga * qty, sisa = 0 WHERE id = ?")->execute([$id]);

            $pdo->commit();
            flash('success', 'Item "' . $h['nama_produk'] . '" atas nama ' . $h['nama'] . ' LUNAS. Transaksi ' . $kode . ' tercatat.');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Gagal: ' . $e->getMessage());
        }
    }
    redirect('hutang.php');
}

/* ============================================================
   AKSI: HAPUS 1 ITEM HUTANG
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'hapus_item') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $pdo->prepare("DELETE FROM hutang WHERE id = ?")->execute([$id]);
            flash('success', 'Item hutang berhasil dihapus.');
        } catch (PDOException $e) {
            flash('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }
    redirect('hutang.php');
}

/* ============================================================
   AKSI: HAPUS SEMUA HUTANG KONSUMEN
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'hapus_konsumen') {
    $nama = trim($_POST['nama'] ?? '');
    if ($nama !== '') {
        try {
            $pdo->prepare("DELETE FROM hutang WHERE nama = ?")->execute([$nama]);
            flash('success', 'Semua hutang atas nama ' . $nama . ' dihapus.');
        } catch (PDOException $e) {
            flash('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }
    redirect('hutang.php');
}

/* ============================================================
   DATA
   ============================================================ */
$statusFilter = $_GET['status'] ?? 'semua';

$rekapKonsumen = $pdo->query("
    SELECT nama,
           COUNT(*)         AS jml_item,
           SUM(qty)         AS total_qty,
           SUM(harga * qty) AS total_belanja,
           SUM(dibayar)     AS total_dibayar,
           SUM(sisa)        AS total_sisa,
           MIN(tanggal)     AS tgl_awal,
           MAX(tanggal)     AS tgl_akhir
    FROM hutang
    GROUP BY nama
    ORDER BY
      CASE WHEN SUM(sisa) > 0 THEN 0 ELSE 1 END,
      MAX(tanggal) DESC,
      nama ASC
")->fetchAll();

$grandTotalSisa = 0;
$grandTotalDibayar = 0;
foreach ($rekapKonsumen as $r) {
    $grandTotalSisa    += (float)$r['total_sisa'];
    $grandTotalDibayar += (float)$r['total_dibayar'];
}

/* Detail semua konsumen (untuk expand inline) */
$detailByKonsumen = [];
if ($rekapKonsumen) {
    $namaList     = array_column($rekapKonsumen, 'nama');
    $placeholders = implode(',', array_fill(0, count($namaList), '?'));

    $stmt = $pdo->prepare("
        SELECT h.*, p.nama AS nama_produk, p.kode AS kode_produk, p.satuan
        FROM hutang h
        JOIN produk p ON p.id = h.produk_id
        WHERE h.nama IN ($placeholders)
        ORDER BY h.nama ASC, h.sisa > 0 DESC, h.id DESC
    ");
    $stmt->execute($namaList);

    foreach ($stmt->fetchAll() as $row) {
        $detailByKonsumen[$row['nama']][] = $row;
    }
}

$pageTitle    = 'Hutang';
$pageSubtitle = 'Daftar & pelunasan hutang konsumen';
require_once 'includes/header.php';
?>

<!-- ===== STATS ===== -->
<div class="hutang-stats">
  <div class="stat">
    <div class="stat-label">Konsumen Berhutang</div>
    <div class="stat-value">
      <?php
        $jmlBelum = 0;
        foreach ($rekapKonsumen as $r) if ((float)$r['total_sisa'] > 0) $jmlBelum++;
        echo $jmlBelum;
      ?>
    </div>
    <div class="stat-sub">dari <?= count($rekapKonsumen) ?> total konsumen</div>
  </div>
  <div class="stat">
    <div class="stat-label">Total Sisa Hutang</div>
    <div class="stat-value text-danger"><?= rupiah($grandTotalSisa) ?></div>
    <div class="stat-sub">belum lunas</div>
  </div>
  <div class="stat">
    <div class="stat-label">Total Sudah Dibayar</div>
    <div class="stat-value text-green"><?= rupiah($grandTotalDibayar) ?></div>
    <div class="stat-sub">akumulasi DP & cicilan</div>
  </div>
  <div class="stat">
    <div class="stat-label">Status</div>
    <div class="stat-value">
      <?php if ($grandTotalSisa > 0): ?>
        <span class="tag tag-red">BELUM LUNAS</span>
      <?php else: ?>
        <span class="tag tag-green">SEMUA LUNAS</span>
      <?php endif; ?>
    </div>
    <div class="stat-sub">kondisi saat ini</div>
  </div>
</div>

<!-- ===== REKAP + DETAIL PER KONSUMEN ===== -->
<div class="card">
  <div class="card-head">
    <h2>👥 Rekap Hutang per Konsumen</h2>
    <?php if ($grandTotalSisa > 0): ?>
      <span class="tag tag-red">Sisa Total: <?= rupiah($grandTotalSisa) ?></span>
    <?php else: ?>
      <span class="tag tag-green">Semua Lunas 🎉</span>
    <?php endif; ?>
  </div>

  <!-- Status filter tabs -->
  <div class="status-tabs">
    <a href="hutang.php?status=semua"
       class="status-tab <?= $statusFilter === 'semua' ? 'active' : '' ?>">
      📋 Semua
    </a>
    <a href="hutang.php?status=belum"
       class="status-tab <?= $statusFilter === 'belum' ? 'active' : '' ?>">
      ⏳ Belum Lunas
    </a>
    <a href="hutang.php?status=lunas"
       class="status-tab <?= $statusFilter === 'lunas' ? 'active' : '' ?>">
      ✅ Sudah Lunas
    </a>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:30px"></th>
          <th>Nama Konsumen</th>
          <th class="text-center">Jml Item</th>
          <th class="text-right">Total Belanja</th>
          <th class="text-right">Sudah Dibayar</th>
          <th class="text-right">Sisa Hutang</th>
          <th class="text-center">Status</th>
          <th class="text-center" style="width:120px">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rekapKonsumen): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <span class="emoji">🎉</span>
            Tidak ada data hutang.
          </div>
        </td></tr>
      <?php else:
        $adaData = false;
        foreach ($rekapKonsumen as $r):
          $sisa   = (float)$r['total_sisa'];
          $lunas  = ($sisa <= 0);

          if ($statusFilter === 'belum' && $lunas) continue;
          if ($statusFilter === 'lunas' && !$lunas) continue;

          $adaData = true;
          $details = $detailByKonsumen[$r['nama']] ?? [];
          $rowId   = 'detail-' . md5($r['nama']);
      ?>
        <tr class="row-konsumen <?= $lunas ? 'row-lunas' : '' ?>"
            data-target="<?= $rowId ?>">
          <td class="text-center">
            <span class="toggle-icon" id="icon-<?= $rowId ?>">▶</span>
          </td>
          <td class="strong">
            <?= e($r['nama']) ?>
            <small class="text-muted" style="font-weight:400">
              (<?= count($details) ?> item)
            </small>
          </td>
          <td class="text-center"><?= (int)$r['jml_item'] ?> produk</td>
          <td class="text-right"><?= rupiah($r['total_belanja']) ?></td>
          <td class="text-right text-green"><?= rupiah($r['total_dibayar']) ?></td>
          <td class="text-right strong <?= $lunas ? 'text-green' : 'text-danger' ?>">
            <?= $lunas ? '✓ LUNAS' : rupiah($sisa) ?>
          </td>
          <td class="text-center">
            <?php if ($lunas): ?>
              <span class="tag tag-green">LUNAS</span>
            <?php else: ?>
              <span class="tag tag-red">BELUM LUNAS</span>
            <?php endif; ?>
          </td>
          <td class="text-center" style="white-space:nowrap" onclick="event.stopPropagation()">
            <?php if (!$lunas): ?>
              <button type="button" class="btn btn-primary btn-sm"
                      onclick="bukaBayarLunas('<?= e(addslashes($r['nama'])) ?>', <?= (int)$sisa ?>)"
                      title="Lunasi Semua">
                ✓
              </button>
            <?php endif; ?>

            <button type="button" class="btn btn-danger btn-sm"
                    onclick="bukaHapusKonsumen('<?= e(addslashes($r['nama'])) ?>', <?= (int)$r['jml_item'] ?>)"
                    title="Hapus Semua Riwayat">
              🗑
            </button>
          </td>
        </tr>

        <tr class="row-detail" id="<?= $rowId ?>">
          <td colspan="8">
            <div class="detail-inner" id="inner-<?= $rowId ?>">
              <div class="detail-content">

                <?php if (!$lunas): ?>
                <!-- PANEL PELUNASAN -->
                <div class="pelunasan-panel">
                  <div class="pelunasan-grid">

                    <div class="pelunasan-section lunas">
                      <h4>✓ Bayar LUNAS (Full)</h4>
                      <p>Lunasi semua sisa <strong><?= rupiah($sisa) ?></strong> sekaligus.</p>
                      <form method="post"
                            onsubmit="return confirm('Lunasi SEMUA hutang <?= e($r['nama']) ?>?\n\nTotal: <?= rupiah($sisa) ?>')">
                        <input type="hidden" name="act" value="bayar">
                        <input type="hidden" name="nama" value="<?= e($r['nama']) ?>">
                        <input type="hidden" name="nominal" value="0">
                        <button class="btn btn-primary btn-sm">✓ LUNAS — <?= rupiah($sisa) ?></button>
                      </form>
                    </div>

                    <div class="pelunasan-section cicil">
                      <h4>💰 Bayar Sebagian (Cicilan)</h4>
                      <p>Masukkan nominal cicilan (min. Rp 100).</p>
                      <form method="post" class="cicilan-form"
                            onsubmit="return confirm('Catat cicilan untuk <?= e($r['nama']) ?>?')">
                        <input type="hidden" name="act" value="bayar">
                        <input type="hidden" name="nama" value="<?= e($r['nama']) ?>">
                        <input type="number" name="nominal" min="100" max="<?= (int)$sisa ?>"
                               step="100" placeholder="Nominal" required>
                        <input type="text" name="keterangan" placeholder="Ket. (opsional)">
                        <button class="btn btn-cicil btn-sm">💰 Cicil</button>
                      </form>
                    </div>

                  </div>
                </div>
                <?php endif; ?>

                <!-- TABEL DETAIL ITEM -->
                <div class="detail-table-wrap">
                  <table style="margin:0">
                    <thead>
                      <tr>
                        <th>Kode</th>
                        <th>Produk</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Dibayar</th>
                        <th class="text-right">Sisa</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width:110px">Aksi</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php if (!$details): ?>
                      <tr><td colspan="9">
                        <div class="empty-state">Tidak ada detail.</div>
                      </td></tr>
                    <?php else: foreach ($details as $d):
                      $subtotal  = (float)$d['harga'] * (int)$d['qty'];
                      $dibayar   = (float)$d['dibayar'];
                      $sisaItem  = (float)$d['sisa'];
                      $isLunas   = ($sisaItem <= 0);
                    ?>
                      <tr class="<?= $isLunas ? 'lunas-row' : '' ?>">
                        <td><span class="tag tag-gray"><?= e($d['kode_produk']) ?></span></td>
                        <td><?= e($d['nama_produk']) ?></td>
                        <td class="text-center"><?= (int)$d['qty'] ?> <?= e($d['satuan']) ?></td>
                        <td class="text-right"><?= rupiah($d['harga']) ?></td>
                        <td class="text-right"><?= rupiah($subtotal) ?></td>
                        <td class="text-right text-green"><?= rupiah($dibayar) ?></td>
                        <td class="text-right strong <?= $isLunas ? 'text-green' : 'text-danger' ?>">
                          <?= $isLunas ? 'Rp 0' : rupiah($sisaItem) ?>
                        </td>
                        <td class="text-center">
                          <?php if ($isLunas): ?>
                            <span class="tag tag-green">✓ LUNAS</span>
                          <?php else: ?>
                            <span class="tag tag-red">BELUM</span>
                          <?php endif; ?>
                        </td>
                        <td class="text-center" style="white-space:nowrap">
                          <?php if (!$isLunas): ?>
                            <form method="post" style="display:inline"
                                  onsubmit="return confirm('Lunasi item ini?\n\n<?= e($d['nama_produk']) ?> — Sisa: <?= rupiah($sisaItem) ?>')">
                              <input type="hidden" name="act" value="bayar_item">
                              <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                              <button class="btn btn-primary btn-sm" title="Lunasi Item">✓</button>
                            </form>
                          <?php endif; ?>

                          <button type="button" class="btn btn-danger btn-sm"
                                  onclick="bukaHapusItem('<?= (int)$d['id'] ?>', '<?= e(addslashes($d['nama_produk'])) ?>', '<?= e(addslashes($r['nama'])) ?>', <?= (int)$d['qty'] ?>)"
                                  title="Hapus Item">
                            🗑
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>

              </div>
            </div>
          </td>
        </tr>
      <?php endforeach;
        if (!$adaData): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <span class="emoji">🔍</span>
            Tidak ada data untuk filter yang dipilih.
          </div>
        </td></tr>
      <?php endif; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== MODAL BAYAR LUNAS ===== -->
<div class="modal-overlay no-print" id="modalBayarLunas">
  <div class="modal-box">
    <div class="modal-head">
      <h3>💳 Lunasi Hutang?</h3>
      <button type="button" class="modal-close" onclick="tutupModalBayar()">×</button>
    </div>
    <div class="modal-body">
      <p style="margin:0 0 8px; font-size:13px; color:#64748b;">Lunasi <strong>semua sisa hutang</strong> atas nama:</p>
      <p class="modal-kode" id="modalBayarNama">-</p>

      <div class="modal-info">
        <div>
          <span>Total Sisa</span>
          <strong id="modalBayarTotal">Rp 0</strong>
        </div>
      </div>

      <p class="modal-warn">⚠️ Transaksi pelunasan akan otomatis tercatat.</p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-outline" onclick="tutupModalBayar()">Batal</button>
      <form method="post" style="display:inline">
        <input type="hidden" name="act" value="bayar">
        <input type="hidden" name="nama" id="modalBayarNamaInput">
        <input type="hidden" name="nominal" value="0">
        <button type="submit" class="btn btn-primary">✓ Ya, Lunasi</button>
      </form>
    </div>
  </div>
</div>

<!-- ===== MODAL HAPUS ===== -->
<div class="modal-overlay no-print" id="modalHapus">
  <div class="modal-box">
    <div class="modal-head">
      <h3 style="color: var(--danger, #dc2626);">Hapus Data?</h3>
      <button type="button" class="modal-close" onclick="tutupModalHapus()">×</button>
    </div>
    <div class="modal-body">
      <p id="modalHapusTeks" style="margin:0 0 4px; font-size:13px; color:#334155;">Anda akan menghapus data ini.</p>

      <div class="modal-info">
        <div>
          <span id="modalHapusLabel">Target:</span>
          <strong id="modalHapusNama" style="font-size:16px;">-</strong>
        </div>
        <div style="margin-top:10px; padding-top:10px; border-top:1px solid #e2e8f0;">
          <span id="modalHapusDetail" style="color:#64748b;">-</span>
        </div>
      </div>

      <p class="modal-warn danger">⚠️ Data yang dihapus tidak dapat dikembalikan.</p>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-outline" onclick="tutupModalHapus()">Batal</button>
      <form method="post" style="display:inline" id="formHapus">
        <input type="hidden" name="act" id="hapusAct">
        <input type="hidden" name="nama" id="hapusNamaInput">
        <input type="hidden" name="id" id="hapusIdInput">
        <button type="submit" class="btn btn-danger">Ya, Hapus</button>
      </form>
    </div>
  </div>
</div>

<script>
function bukaBayarLunas(nama, total) {
  document.getElementById('modalBayarNama').textContent = nama;
  document.getElementById('modalBayarTotal').textContent =
    'Rp ' + Number(total).toLocaleString('id-ID');
  document.getElementById('modalBayarNamaInput').value = nama;
  document.getElementById('modalBayarLunas').classList.add('show');
}

function tutupModalBayar() {
  document.getElementById('modalBayarLunas').classList.remove('show');
}

document.getElementById('modalBayarLunas').addEventListener('click', function (e) {
  if (e.target === this) tutupModalBayar();
});

function bukaHapusKonsumen(nama, jmlItem) {
  document.getElementById('modalHapusTeks').textContent = 'Anda akan menghapus riwayat penjualan hutang konsumen ini:';
  document.getElementById('modalHapusLabel').textContent = 'Nama Konsumen:';
  document.getElementById('modalHapusNama').textContent = nama;
  document.getElementById('modalHapusDetail').innerHTML = 'Total <strong>' + jmlItem + ' item</strong> akan dihapus.';

  document.getElementById('hapusAct').value = 'hapus_konsumen';
  document.getElementById('hapusNamaInput').value = nama;
  document.getElementById('hapusIdInput').value = '';

  document.getElementById('modalHapus').classList.add('show');
}

function bukaHapusItem(id, namaProduk, namaKonsumen, qty) {
  document.getElementById('modalHapusTeks').textContent = 'Anda akan menghapus 1 item hutang dari daftar:';
  document.getElementById('modalHapusLabel').textContent = 'Produk:';
  document.getElementById('modalHapusNama').textContent = namaProduk;
  document.getElementById('modalHapusDetail').innerHTML = 'Atas nama: <strong>' + namaKonsumen + '</strong> (Qty: ' + qty + ')';

  document.getElementById('hapusAct').value = 'hapus_item';
  document.getElementById('hapusNamaInput').value = '';
  document.getElementById('hapusIdInput').value = id;

  document.getElementById('modalHapus').classList.add('show');
}

function tutupModalHapus() {
  document.getElementById('modalHapus').classList.remove('show');
}

document.getElementById('modalHapus').addEventListener('click', function (e) {
  if (e.target === this) tutupModalHapus();
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    tutupModalBayar();
    tutupModalHapus();
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>