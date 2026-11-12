<?php
    require_once 'config/database.php';
    requireLogin();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cart       = json_decode($_POST['cart'] ?? '[]', true) ?: [];
        
        $bayar_raw  = $_POST['bayar'] ?? '0';
        $bayar      = (float)str_replace('.', '', $bayar_raw);
        
        $keterangan = trim($_POST['keterangan'] ?? '');
        $mode       = $_POST['mode'] ?? 'tunai';
        $namaHutang = trim($_POST['nama_hutang'] ?? '');
        
        $redirect   = $_POST['redirect'] ?? 'cetak';

        if (!$cart) {
            flash('error', 'Keranjang masih kosong.');
            redirect('kasir.php');
        }

        if ($mode === 'hutang' && $namaHutang === '') {
            flash('error', 'Nama orang untuk hutang wajib diisi.');
            redirect('kasir.php');
        }

        try {
            $pdo->beginTransaction();

            $ids  = array_map(fn($i) => (int)$i['id'], $cart);
            $in   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id, nama, harga_jual, stok FROM produk WHERE id IN ($in) FOR UPDATE");
            $stmt->execute($ids);

            $produkDb = [];
            foreach ($stmt->fetchAll() as $p) $produkDb[(int)$p['id']] = $p;

            $total = 0;
            foreach ($cart as $item) {
                $pid = (int)$item['id'];
                $qty = (int)$item['qty'];
                if (!isset($produkDb[$pid])) throw new Exception('Produk tidak ditemukan.');
                if ($qty < 1) throw new Exception('Jumlah item tidak valid.');
                if ($produkDb[$pid]['stok'] < $qty) {
                    throw new Exception('Stok "' . $produkDb[$pid]['nama'] . '" tidak mencukupi.');
                }
                $total += (float)$produkDb[$pid]['harga_jual'] * $qty;
            }

            if ($mode === 'tunai' && $bayar < $total) {
                throw new Exception('Nominal pembayaran kurang dari total tagihan.');
            }

            if ($mode === 'hutang') {
                $bayarSekarang = max(0, min($bayar, $total));
                $sisaHutang    = $total - $bayarSekarang;

                if ($sisaHutang <= 0) {
                    throw new Exception('Jika uang cukup, gunakan mode Tunai.');
                }

                if ($bayarSekarang > 0) {
                    $kode = generateKode('TRX', $pdo, 'transaksi', 'kode');
                    $pdo->prepare("INSERT INTO transaksi (kode, user_id, jenis, total, bayar, kembalian, keterangan)
                                VALUES (?,?,'penjualan',?,?,0,?)")
                        ->execute([
                            $kode,
                            user()['id'],
                            $bayarSekarang,
                            $bayarSekarang,
                            'DP hutang dari ' . $namaHutang
                        ]);
                    $trxId = (int)$pdo->lastInsertId();

                    $insDet = $pdo->prepare("INSERT INTO transaksi_detail (transaksi_id, produk_id, qty, harga, subtotal) VALUES (?,?,?,?,?)");
                    foreach ($cart as $item) {
                        $pid   = (int)$item['id'];
                        $qty   = (int)$item['qty'];
                        $harga = (float)$produkDb[$pid]['harga_jual'];
                        $insDet->execute([$trxId, $pid, $qty, $harga, $qty * $harga]);
                    }
                }

                $updStok = $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
                foreach ($cart as $item) {
                    $updStok->execute([(int)$item['qty'], (int)$item['id']]);
                }

                $insHutang = $pdo->prepare("
                    INSERT INTO hutang (nama, produk_id, qty, harga, dibayar, sisa, keterangan, tanggal)
                    VALUES (?,?,?,?,?,?,?,CURDATE())
                ");

                $ketHutang = ($keterangan ? $keterangan . ' | ' : '') .
                            'DP total: ' . rupiah($bayarSekarang);

                foreach ($cart as $item) {
                    $pid          = (int)$item['id'];
                    $qty          = (int)$item['qty'];
                    $harga        = (float)$produkDb[$pid]['harga_jual'];
                    $subtotalItem = $harga * $qty;

                    $porsiDibayar = ($total > 0)
                        ? ($subtotalItem / $total) * $bayarSekarang
                        : 0;

                    $porsiDibayar = round($porsiDibayar, 2);
                    $sisaItem     = $subtotalItem - $porsiDibayar;

                    $insHutang->execute([
                        $namaHutang,
                        $pid,
                        $qty,
                        $harga,
                        $porsiDibayar,
                        $sisaItem,
                        $ketHutang
                    ]);
                }

                $pdo->commit();

                if ($bayarSekarang > 0) {
                    flash('success', 'DP ' . rupiah($bayarSekarang) . ' tercatat. Sisa hutang ' . rupiah($sisaHutang) . ' atas nama ' . $namaHutang . '.');
                } else {
                    flash('success', 'Transaksi jadi hutang ' . $namaHutang . ' sebesar ' . rupiah($sisaHutang) . '.');
                }

                redirect('kasir.php');
            }

            $kode = generateKode('TRX', $pdo, 'transaksi', 'kode');
            $ins  = $pdo->prepare("INSERT INTO transaksi (kode, user_id, jenis, total, bayar, kembalian, keterangan)
                                VALUES (?,?,'penjualan',?,?,?,?)");
            $ins->execute([$kode, user()['id'], $total, $bayar, $bayar - $total, $keterangan]);
            $trxId = (int)$pdo->lastInsertId();

            $insDet  = $pdo->prepare("INSERT INTO transaksi_detail (transaksi_id, produk_id, qty, harga, subtotal) VALUES (?,?,?,?,?)");
            $updStok = $pdo->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");

            foreach ($cart as $item) {
                $pid   = (int)$item['id'];
                $qty   = (int)$item['qty'];
                $harga = (float)$produkDb[$pid]['harga_jual'];
                $insDet->execute([$trxId, $pid, $qty, $harga, $qty * $harga]);
                $updStok->execute([$qty, $pid]);
            }

            $pdo->commit();
            
            if ($redirect === 'simpan') {
                flash('success', 'Transaksi berhasil disimpan.');
                redirect('kasir.php');
            }

            redirect('struk.php?id=' . $trxId);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
            redirect('kasir.php');
        }
    }

    $pageTitle    = 'Kasir';
    $pageSubtitle = 'Transaksi penjualan tunai';

    $produk = $pdo->query("SELECT * FROM produk ORDER BY nama ASC")->fetchAll();

    require_once 'includes/header.php';
    ?>

    <!-- TOAST INFORMASI MENGAMBANG -->
    <div id="floatingInfo" class="floating-info"></div>

    <!-- MODAL KONFIRMASI KUSTOM -->
    <div id="confirmModal" class="kasir-modal-overlay">
        <div class="kasir-modal-box">
            <div class="kasir-modal-header">
                <div class="kasir-modal-title">Konfirmasi Transaksi</div>
                <button type="button" class="kasir-modal-close" id="btnCloseModal">&times;</button>
            </div>
            <div class="kasir-modal-body">
                <div class="kasir-modal-desc">Pastikan detail transaksi di bawah ini sudah benar.</div>
                
                <div class="kasir-summary-box" id="confirmModalBody">
                    <!-- Diisi oleh Javascript -->
                </div>

                <div class="kasir-warning-box">
                    <span>⚠️</span>
                    <span>Transaksi yang sudah disimpan tidak dapat dibatalkan.</span>
                </div>
            </div>
            <div class="kasir-modal-actions">
                <button type="button" class="btn btn-cancel" id="btnCancelConfirm">Batal</button>
                <button type="button" class="btn btn-confirm" id="btnOkConfirm">Ya, Simpan</button>
            </div>
        </div>
    </div>

    <form method="post" id="formKasir">
    <input type="hidden" name="cart" id="cartInput">
    <input type="hidden" name="mode" id="modeInput" value="tunai">
    <input type="hidden" name="redirect" id="redirectInput" value="cetak">

    <div class="pos-layout">

        <!-- ===== PRODUK ===== -->
        <div class="card mb-0">
        <div class="card-head">
            <h2>Daftar Produk</h2>
            <input type="text" id="searchProduk" placeholder="Cari produk..." style="max-width:230px">
        </div>

        <div style="padding: 10px 16px; display:flex; gap:8px; flex-wrap:wrap; border-bottom:1px solid #e5e7eb;">
            <button type="button" class="btn btn-sm btn-primary filter-kategori" data-kategori="">Semua</button>
            <?php foreach (['Minuman', 'Makanan', 'ATK', 'Es Krim'] as $kb): ?>
            <button type="button" class="btn btn-sm btn-outline filter-kategori" data-kategori="<?= e($kb) ?>">
                <?= e($kb) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <div class="card-body">
            <div class="produk-grid" id="produkGrid">
            <?php foreach ($produk as $p): ?>
                <div class="produk-item <?= $p['stok'] <= 0 ? 'out' : '' ?>"
                    data-id="<?= (int)$p['id'] ?>"
                    data-nama="<?= e($p['nama']) ?>"
                    data-kategori="<?= e($p['kategori'] ?? '') ?>"
                    data-harga="<?= (float)$p['harga_jual'] ?>"
                    data-stok="<?= (int)$p['stok'] ?>">
                <div class="produk-img">
                    <img src="<?= e(produkImage($p['gambar'])) ?>" alt="<?= e($p['nama']) ?>" loading="lazy">
                </div>
                <div class="pname"><?= e($p['nama']) ?></div>
                <div class="pprice"><?= rupiah($p['harga_jual']) ?></div>
                <div class="pstock <?= $p['stok'] <= 10 ? 'low' : '' ?>">
                    Stok: <?= (int)$p['stok'] ?> <?= e($p['satuan']) ?>
                </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        </div>

        <!-- ===== KERANJANG ===== -->
        <div class="card mb-0">
        <div class="card-head"><h2>Keranjang</h2><span class="tag tag-green" id="cartCount">0 item</span></div>
        <div class="card-body">

            <div class="cart-list" id="cartList">
            <div class="empty" style="padding:26px 0">Belum ada item dipilih</div>
            </div>

            <div class="summary">
            <div class="summary-row">
                <span>Total Tagihan</span>
                <span class="val" id="totalText">Rp 0</span>
            </div>

            <div class="form-group">
                <label>Nominal Bayar</label>
                <input type="text" name="bayar" id="bayarInput" inputmode="numeric" placeholder="0" autocomplete="off">
            </div>

            <div class="form-group" style="margin-top:10px">
                <label>Mode Pembayaran</label>
                <div style="display:flex; gap:8px; margin-top:6px">
                <button type="button" class="btn btn-primary btn-mode" data-mode="tunai" style="flex:1">
                    💵 Tunai
                </button>
                <button type="button" class="btn btn-outline btn-mode" data-mode="hutang" style="flex:1">
                    📝 Hutang
                </button>
                </div>
            </div>

            <div class="form-group" id="namaHutangGroup" style="display:none; margin-top:10px">
                <label>Nama Orang (Hutang) <span class="req">*</span></label>
                <input type="text" name="nama_hutang" id="namaHutangInput" placeholder="Contoh: Budi">
            </div>

            <div class="kembalian-box" id="kembalianBox">
                <span id="kembalianLabel">Kembalian</span>
                <span id="kembalianText">Rp 0</span>
            </div>

            <div class="form-group">
                <label>Keterangan</label>
                <input type="text" name="keterangan" placeholder="Opsional...">
            </div>

            <div style="display:flex; gap:8px; margin-top:14px;">
                <button type="button" class="btn btn-outline btn-block" style="flex:1" id="btnSimpanSaja" onclick="submitForm('simpan')">
                    💾 Simpan Saja
                </button>
                <button type="button" class="btn btn-primary btn-block" style="flex:1.5" id="btnSimpanCetak" onclick="submitForm('cetak')">
                    🖨️ Simpan & Cetak
                </button>
            </div>
            <button type="button" class="btn btn-outline btn-block" style="margin-top:8px" id="resetBtn">
                Kosongkan Keranjang
            </button>
            </div>

        </div>
        </div>

    </div>
    </form>

    <script>
    const cart = {};
    const grid      = document.getElementById('produkGrid');
    const cartList  = document.getElementById('cartList');
    const cartInput = document.getElementById('cartInput');
    const totalText = document.getElementById('totalText');
    const bayarIn   = document.getElementById('bayarInput');
    const kembaliTx = document.getElementById('kembalianText');
    const kembaliLb = document.getElementById('kembalianLabel');
    const cartCount = document.getElementById('cartCount');
    const modeInput = document.getElementById('modeInput');
    const namaGroup = document.getElementById('namaHutangGroup');
    const namaInput = document.getElementById('namaHutangInput');
    const redirectInput = document.getElementById('redirectInput');
    
    const floatingInfo = document.getElementById('floatingInfo');
    const confirmModal = document.getElementById('confirmModal');
    const confirmBody  = document.getElementById('confirmModalBody');
    const btnCancel    = document.getElementById('btnCancelConfirm');
    const btnOk        = document.getElementById('btnOkConfirm');
    const btnClose     = document.getElementById('btnCloseModal');
    
    let floatingTimeout;
    let currentMode = 'tunai';
    let isConfirmed = false;

    const rp = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');

    let filterKategori = '';
    let filterCari     = '';

    function showFloatingInfo(pesan, tipe = 'info', durasi = 2500) {
        clearTimeout(floatingTimeout);
        floatingInfo.textContent = pesan;
        floatingInfo.className = 'floating-info show ' + tipe;
        floatingTimeout = setTimeout(() => {
            floatingInfo.classList.remove('show');
        }, durasi);
    }

    function formatAngka(angka) {
        let val = angka.toString().replace(/\D/g, ''); 
        if (val === '') return '';
        return parseInt(val, 10).toLocaleString('id-ID'); 
    }

    function getAngkaMurni(str) {
        if (!str) return 0;
        return parseFloat(str.replace(/\./g, '')) || 0;
    }

    bayarIn.addEventListener('keyup', function(e) {
        let cursorPos = this.selectionStart;
        let oldLength = this.value.length;
        
        this.value = formatAngka(this.value);
        
        let newLength = this.value.length;
        cursorPos = cursorPos + (newLength - oldLength);
        this.setSelectionRange(cursorPos, cursorPos);
        
        hitungKembalian();
    });

    grid.addEventListener('click', e => {
        const el = e.target.closest('.produk-item');
        if (!el) return;
        const id = el.dataset.id;
        if (!cart[id]) {
            cart[id] = { id, nama: el.dataset.nama, harga: parseFloat(el.dataset.harga), qty: 0, stok: parseInt(el.dataset.stok) };
        }
        if (cart[id].qty >= cart[id].stok) { 
            showFloatingInfo('Stok tidak mencukupi.', 'error'); 
            return; 
        }
        cart[id].qty++;
        render();
    });

    cartList.addEventListener('click', e => {
        const id = e.target.dataset.id;
        if (!id) return;
        if (e.target.classList.contains('plus'))  { if (cart[id].qty < cart[id].stok) cart[id].qty++; }
        if (e.target.classList.contains('minus')) { cart[id].qty--; if (cart[id].qty <= 0) delete cart[id]; }
        if (e.target.classList.contains('del'))   { delete cart[id]; }
        render();
    });

    document.getElementById('resetBtn').addEventListener('click', () => {
        if (confirm('Kosongkan keranjang?')) { 
            for (const k in cart) delete cart[k]; 
            bayarIn.value = '';
            render(); 
        }
    });

    document.querySelectorAll('.btn-mode').forEach(btn => {
        btn.addEventListener('click', function() {
            currentMode = this.dataset.mode;
            modeInput.value = currentMode;

            document.querySelectorAll('.btn-mode').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline');
            });
            this.classList.remove('btn-outline');
            this.classList.add('btn-primary');

            if (currentMode === 'hutang') {
                namaGroup.style.display = '';
                namaInput.required = true;
                kembaliLb.textContent = 'Sisa Hutang';
                bayarIn.placeholder = '0 (boleh kosong = full hutang)';
                showFloatingInfo('Mode Hutang: Isi Nama Orang & Nominal DP (jika ada).', 'info');
            } else {
                namaGroup.style.display = 'none';
                namaInput.required = false;
                kembaliLb.textContent = 'Kembalian';
                bayarIn.placeholder = '0';
                showFloatingInfo('Mode Tunai Aktif.', 'info');
            }
            hitungKembalian();
        });
    });

    function render() {
        const items = Object.values(cart);
        if (!items.length) {
            cartList.innerHTML = '<div class="empty" style="padding:26px 0">Belum ada item dipilih</div>';
        } else {
            cartList.innerHTML = items.map(i => `
            <div class="cart-row">
                <div style="flex:1">
                <div class="cname">${i.nama}</div>
                <div class="cprice">${rp(i.harga)} × ${i.qty} = <strong>${rp(i.harga * i.qty)}</strong></div>
                </div>
                <div class="qty-ctrl">
                <button type="button" class="minus" data-id="${i.id}">−</button>
                <span>${i.qty}</span>
                <button type="button" class="plus" data-id="${i.id}">+</button>
                </div>
                <button type="button" class="del" data-id="${i.id}">×</button>
            </div>`).join('');
        }

        const total = items.reduce((s, i) => s + i.harga * i.qty, 0);
        totalText.textContent = rp(total);
        cartCount.textContent = items.reduce((s, i) => s + i.qty, 0) + ' item';
        cartInput.value = JSON.stringify(items.map(i => ({ id: i.id, qty: i.qty })));
        hitungKembalian();
    }

    function hitungKembalian() {
        const total = Object.values(cart).reduce((s, i) => s + i.harga * i.qty, 0);
        const bayar = getAngkaMurni(bayarIn.value);

        if (currentMode === 'hutang') {
            const sisa = total - bayar;
            kembaliTx.textContent = rp(sisa > 0 ? sisa : 0);
            kembaliTx.style.color = '#dc2626';
        } else {
            const kembali = bayar - total;
            kembaliTx.textContent = rp(kembali > 0 ? kembali : 0);
            kembaliTx.style.color = kembali < 0 ? '#dc2626' : '';
        }
    }

    function applyFilter() {
        const q = filterCari.toLowerCase();
        grid.querySelectorAll('.produk-item').forEach(el => {
            const cocokNama = el.dataset.nama.toLowerCase().includes(q);
            const cocokKat  = filterKategori === '' || el.dataset.kategori === filterKategori;
            el.style.display = (cocokNama && cocokKat) ? '' : 'none';
        });
    }

    document.getElementById('searchProduk').addEventListener('input', function () {
        filterCari = this.value;
        applyFilter();
    });

    document.querySelectorAll('.filter-kategori').forEach(btn => {
        btn.addEventListener('click', function () {
            filterKategori = this.dataset.kategori;
            document.querySelectorAll('.filter-kategori').forEach(b => {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline');
            });
            this.classList.remove('btn-outline');
            this.classList.add('btn-primary');
            applyFilter();
        });
    });

    function submitForm(action) {
        redirectInput.value = action;
        document.getElementById('formKasir').dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    }

    document.getElementById('formKasir').addEventListener('submit', e => {
        if (isConfirmed) return;
        e.preventDefault();

        if (!Object.keys(cart).length) {
            showFloatingInfo('Keranjang masih kosong.', 'error');
            return;
        }

        const total = Object.values(cart).reduce((s, i) => s + i.harga * i.qty, 0);
        const bayar = getAngkaMurni(bayarIn.value);

        if (currentMode === 'tunai' && bayar < total) {
            showFloatingInfo('Uang bayar kurang dari total. Silakan pilih mode Hutang.', 'error');
            return;
        }

        if (currentMode === 'hutang') {
            if (bayar >= total) {
                showFloatingInfo('Uang sudah cukup. Gunakan mode Tunai.', 'error');
                return;
            }
            if (!namaInput.value.trim()) {
                showFloatingInfo('Nama orang untuk hutang wajib diisi.', 'error');
                namaInput.focus();
                return;
            }
            
            confirmBody.innerHTML = `
                <div class="kasir-summary-row">
                    <span>Nama Konsumen:</span>
                    <strong>${namaInput.value}</strong>
                </div>
                <div class="kasir-summary-row">
                    <span>Total Belanja:</span>
                    <strong>${rp(total)}</strong>
                </div>
                <div class="kasir-summary-row">
                    <span>Dibayar (DP):</span>
                    <strong>${rp(bayar)}</strong>
                </div>
                <div class="kasir-summary-row">
                    <span>Sisa Hutang:</span>
                    <strong style="color:#dc2626;">${rp(total - bayar)}</strong>
                </div>
            `;
            confirmModal.classList.add('show');
        } else {
            isConfirmed = true;
            document.getElementById('formKasir').submit();
        }
    });

    const closeModal = () => confirmModal.classList.remove('show');
    btnCancel.addEventListener('click', closeModal);
    btnClose.addEventListener('click', closeModal);

    btnOk.addEventListener('click', () => {
        confirmModal.classList.remove('show');
        isConfirmed = true;
        document.getElementById('formKasir').submit();
    });

    </script>

    <?php require_once 'includes/footer.php'; ?>