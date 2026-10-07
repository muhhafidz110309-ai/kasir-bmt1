-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 07, 2026 at 06:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kasir_bmt`
--

-- --------------------------------------------------------

--
-- Table structure for table `hutang`
--

CREATE TABLE `hutang` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `produk_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `harga` decimal(15,2) NOT NULL DEFAULT 0.00,
  `dibayar` decimal(15,2) DEFAULT 0.00,
  `sisa` decimal(15,2) DEFAULT 0.00,
  `keterangan` text DEFAULT NULL,
  `tanggal` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hutang`
--

INSERT INTO `hutang` (`id`, `nama`, `produk_id`, `qty`, `harga`, `dibayar`, `sisa`, `keterangan`, `tanggal`, `created_at`) VALUES
(15, 'dewi', 11, 5, 12000.00, 60000.00, 0.00, 'DP total: Rp 50.000', '2026-10-02', '2026-10-02 15:14:31');

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL,
  `kode` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `kategori` varchar(100) NOT NULL,
  `nominal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pengeluaran`
--

INSERT INTO `pengeluaran` (`id`, `kode`, `user_id`, `kategori`, `nominal`, `tanggal`, `keterangan`) VALUES
(1, 'OUT202609297805', 1, 'minuman', 50001.00, '2026-09-29 15:05:22', ''),
(2, 'OUT202609294166', 1, 'Listrik', 200001.00, '2026-09-29 15:05:51', '');

-- --------------------------------------------------------

--
-- Table structure for table `produk`
--

CREATE TABLE `produk` (
  `id` int(11) NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `harga_beli` decimal(15,2) DEFAULT 0.00,
  `harga_jual` decimal(15,2) DEFAULT 0.00,
  `stok` int(11) DEFAULT 0,
  `satuan` varchar(20) DEFAULT 'pcs',
  `satuan_beli` varchar(20) DEFAULT 'pcs',
  `isi_per_satuan` int(11) DEFAULT 1,
  `gambar` varchar(255) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `produk`
--

INSERT INTO `produk` (`id`, `kode`, `nama`, `kategori`, `harga_beli`, `harga_jual`, `stok`, `satuan`, `satuan_beli`, `isi_per_satuan`, `gambar`, `deskripsi`, `created_at`) VALUES
(4, 'PRD0004', 'TEH PUCUK', 'Minuman', 2000.00, 3500.00, 27, 'pcs', 'pcs', 1, 'prd_20260922060915_bb1e1360.png', '', '2026-09-22 02:12:42'),
(6, 'PRD0006', 'S-TEE', 'Minuman', 2000.00, 3000.00, 14, 'pcs', 'pcs', 1, 'prd_20260922061200_213eaad5.png', '', '2026-09-22 02:12:42'),
(7, 'PRD0007', 'Ultra milk coklat', 'Minuman', 5000.00, 7000.00, 14, 'pcs', 'pcs', 1, 'prd_20260922061411_de33d4b2.png', '', '2026-09-22 02:12:42'),
(8, 'PRD0008', 'Buku Tulis', 'ATK', 3500.00, 5000.00, 145, 'pcs', 'pcs', 1, 'prd_20260922060810_0bcf5f8e.png', '', '2026-09-22 02:12:42'),
(9, 'PRD7251', 'MILKU coklat', 'Minuman', 3000.00, 3500.00, 7, 'pcs', 'pcs', 1, 'prd_20260922061601_a508b1a3.jpg', 'susu', '2026-09-22 02:21:56'),
(10, 'PRD1807', 'Susu Ultra All Variant', 'Minuman', 5000.00, 7000.00, 50, 'pcs', 'pcs', 1, 'prd_20260929145937_f5dd723a.jpeg', '', '2026-09-29 12:59:37'),
(11, 'PRD0831', 'Dimsum Mentai', 'Makanan', 10000.00, 12000.00, 7, 'pcs', 'pcs', 1, 'prd_20260930061434_e745f33e.jpg', '', '2026-09-30 04:14:34'),
(13, 'PRD7007', 'Oasis 600ml', 'Minuman', 35000.00, 2000.00, 19, 'pcs', 'dus', 24, 'prd_20260930064541_2987ede6.png', 'Oasis BMT', '2026-09-30 04:45:41');

-- --------------------------------------------------------

--
-- Table structure for table `simpanan`
--

CREATE TABLE `simpanan` (
  `id` int(11) NOT NULL,
  `nama_penabung` varchar(100) NOT NULL,
  `jenis` varchar(50) NOT NULL,
  `akad` varchar(50) DEFAULT 'Wadiah',
  `saldo` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `simpanan`
--

INSERT INTO `simpanan` (`id`, `nama_penabung`, `jenis`, `akad`, `saldo`, `created_at`) VALUES
(1, 'fakhri', 'Pendidikan', 'Wadiah', 200000.00, '2026-09-23 06:49:44');

-- --------------------------------------------------------

--
-- Table structure for table `simpanan_transaksi`
--

CREATE TABLE `simpanan_transaksi` (
  `id` int(11) NOT NULL,
  `kode` varchar(30) NOT NULL,
  `simpanan_id` int(11) NOT NULL,
  `tipe` enum('setoran','penarikan') NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `saldo_akhir` decimal(15,2) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tanggal` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `simpanan_transaksi`
--

INSERT INTO `simpanan_transaksi` (`id`, `kode`, `simpanan_id`, `tipe`, `nominal`, `saldo_akhir`, `user_id`, `keterangan`, `tanggal`) VALUES
(1, 'STP202609232570', 1, 'setoran', 200000.00, 200000.00, 1, '', '2026-09-23 13:49:44');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `kode` varchar(30) NOT NULL,
  `tanggal` datetime DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `jenis` enum('penjualan','setoran_simpanan','penarikan_simpanan') DEFAULT 'penjualan',
  `total` decimal(15,2) DEFAULT 0.00,
  `bayar` decimal(15,2) DEFAULT 0.00,
  `kembalian` decimal(15,2) DEFAULT 0.00,
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `kode`, `tanggal`, `user_id`, `jenis`, `total`, `bayar`, `kembalian`, `keterangan`) VALUES
(4, 'TRX202609228896', '2026-09-22 11:19:04', 1, 'penjualan', 6500.00, 7000.00, 500.00, ''),
(5, 'TRX202609247184', '2026-09-24 10:46:02', 1, 'penjualan', 15000.00, 20000.00, 5000.00, ''),
(6, 'TRX202609282377', '2026-09-28 09:38:15', 1, 'penjualan', 3500.00, 4000.00, 500.00, ''),
(7, 'TRX202609283421', '2026-09-28 09:38:36', 1, 'penjualan', 3500.00, 4000.00, 500.00, ''),
(8, 'TRX202609307601', '2026-09-30 11:36:03', 1, 'penjualan', 5000.00, 5000.00, 0.00, ''),
(9, 'TRX202610025616', '2026-10-02 09:19:33', 1, 'penjualan', 8500.00, 10000.00, 1500.00, ''),
(10, 'TRX202610020583', '2026-10-02 09:23:16', 1, 'penjualan', 14000.00, 100000.00, 86000.00, ''),
(11, 'TRX202610024537', '2026-10-02 21:11:03', 1, 'penjualan', 5000.00, 5000.00, 0.00, 'DP hutang dari eja'),
(12, 'TRX202610026490', '2026-10-02 21:19:13', 1, 'penjualan', 15000.00, 15000.00, 0.00, 'DP hutang dari ucok'),
(13, 'TRX202610024546', '2026-10-02 21:29:36', 1, 'penjualan', 2000.00, 2000.00, 0.00, 'Pelunasan hutang dari eja'),
(14, 'TRX202610025868', '2026-10-02 21:30:54', 1, 'penjualan', 3901.00, 3901.00, 0.00, 'Cicilan hutang dari ucok'),
(15, 'TRX202610020111', '2026-10-02 21:33:53', 1, 'penjualan', 421.00, 421.00, 0.00, 'Cicilan hutang dari ucok'),
(16, 'TRX202610024188', '2026-10-02 21:34:13', 1, 'penjualan', 421.00, 421.00, 0.00, 'Cicilan hutang dari ucok'),
(17, 'TRX202610026715', '2026-10-02 21:34:30', 1, 'penjualan', 421.00, 421.00, 0.00, 'Cicilan hutang dari ucok'),
(18, 'TRX202610025676', '2026-10-02 21:35:57', 1, 'penjualan', 421.00, 421.00, 0.00, 'Cicilan hutang dari ucok'),
(19, 'TRX202610029890', '2026-10-02 21:39:50', 1, 'penjualan', 20000.00, 20000.00, 0.00, 'DP hutang dari rehan'),
(20, 'TRX202610028526', '2026-10-02 21:58:57', 1, 'penjualan', 5000.00, 5000.00, 0.00, 'DP hutang dari fadil'),
(21, 'TRX202610028576', '2026-10-02 21:59:12', 1, 'penjualan', 7000.00, 7000.00, 0.00, 'Pelunasan hutang dari fadil'),
(22, 'TRX202610029288', '2026-10-02 22:01:45', 1, 'penjualan', 15000.00, 15000.00, 0.00, 'DP hutang dari rehan bego'),
(23, 'TRX202610024906', '2026-10-02 22:02:32', 1, 'penjualan', 10000.00, 10000.00, 0.00, 'DP hutang dari ojan'),
(24, 'TRX202610022451', '2026-10-02 22:08:10', 1, 'penjualan', 20000.00, 20000.00, 0.00, 'DP hutang dari bego'),
(25, 'TRX202610029771', '2026-10-02 22:11:58', 1, 'penjualan', 30000.00, 30000.00, 0.00, 'DP hutang dari ilham'),
(26, 'TRX202610027550', '2026-10-02 22:12:15', 1, 'penjualan', 6000.00, 6000.00, 0.00, 'Pelunasan hutang dari ilham'),
(27, 'TRX202610028644', '2026-10-02 22:14:31', 1, 'penjualan', 50000.00, 50000.00, 0.00, 'DP hutang dari dewi'),
(28, 'TRX202610022721', '2026-10-02 22:15:02', 1, 'penjualan', 10000.00, 10000.00, 0.00, 'Pelunasan hutang dari dewi'),
(29, 'TRX202610060516', '2026-10-06 19:31:34', 1, 'penjualan', 10000.00, 50000.00, 40000.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_detail`
--

CREATE TABLE `transaksi_detail` (
  `id` int(11) NOT NULL,
  `transaksi_id` int(11) NOT NULL,
  `produk_id` int(11) DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `harga` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi_detail`
--

INSERT INTO `transaksi_detail` (`id`, `transaksi_id`, `produk_id`, `qty`, `harga`, `subtotal`) VALUES
(8, 4, 6, 1, 3000.00, 3000.00),
(9, 4, 9, 1, 3500.00, 3500.00),
(10, 5, 6, 1, 3000.00, 3000.00),
(11, 5, 7, 1, 7000.00, 7000.00),
(12, 5, 8, 1, 5000.00, 5000.00),
(13, 6, 9, 1, 3500.00, 3500.00),
(14, 7, 9, 1, 3500.00, 3500.00),
(15, 8, NULL, 2, 2500.00, 5000.00),
(16, 9, 8, 1, 5000.00, 5000.00),
(17, 9, 9, 1, 3500.00, 3500.00),
(18, 10, 11, 1, 12000.00, 12000.00),
(19, 10, 13, 1, 2000.00, 2000.00),
(20, 11, 7, 1, 7000.00, 7000.00),
(21, 12, 8, 1, 5000.00, 5000.00),
(22, 12, 11, 1, 12000.00, 12000.00),
(23, 12, 13, 1, 2000.00, 2000.00),
(24, 13, 7, 1, 2000.00, 2000.00),
(25, 14, 8, 1, 1052.63, 1052.63),
(26, 14, 11, 1, 2526.32, 2526.32),
(27, 19, 6, 3, 3000.00, 9000.00),
(28, 19, 7, 1, 7000.00, 7000.00),
(29, 19, 9, 1, 3500.00, 3500.00),
(30, 19, 13, 1, 2000.00, 2000.00),
(31, 20, 7, 1, 7000.00, 7000.00),
(32, 21, 7, 1, 7000.00, 7000.00),
(33, 22, 4, 1, 3500.00, 3500.00),
(34, 22, 7, 1, 7000.00, 7000.00),
(35, 22, 9, 1, 3500.00, 3500.00),
(36, 22, 13, 1, 2000.00, 2000.00),
(37, 23, 6, 1, 3000.00, 3000.00),
(38, 23, 7, 1, 7000.00, 7000.00),
(39, 23, 13, 1, 2000.00, 2000.00),
(40, 24, 11, 2, 12000.00, 24000.00),
(41, 25, 11, 3, 12000.00, 36000.00),
(42, 26, 11, 3, 12000.00, 6000.00),
(43, 27, 11, 5, 12000.00, 60000.00),
(44, 28, 11, 5, 12000.00, 10000.00),
(45, 29, 8, 2, 5000.00, 10000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','kasir') DEFAULT 'kasir',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '2026-09-22 02:12:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `hutang`
--
ALTER TABLE `hutang`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `produk`
--
ALTER TABLE `produk`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `simpanan`
--
ALTER TABLE `simpanan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_penabung_jenis` (`nama_penabung`,`jenis`);

--
-- Indexes for table `simpanan_transaksi`
--
ALTER TABLE `simpanan_transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `simpanan_id` (`simpanan_id`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaksi_id` (`transaksi_id`),
  ADD KEY `produk_id` (`produk_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `hutang`
--
ALTER TABLE `hutang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `produk`
--
ALTER TABLE `produk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `simpanan`
--
ALTER TABLE `simpanan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `simpanan_transaksi`
--
ALTER TABLE `simpanan_transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `hutang`
--
ALTER TABLE `hutang`
  ADD CONSTRAINT `hutang_ibfk_1` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `simpanan_transaksi`
--
ALTER TABLE `simpanan_transaksi`
  ADD CONSTRAINT `simpanan_transaksi_ibfk_1` FOREIGN KEY (`simpanan_id`) REFERENCES `simpanan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transaksi_detail`
--
ALTER TABLE `transaksi_detail`
  ADD CONSTRAINT `transaksi_detail_ibfk_1` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaksi_detail_ibfk_2` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
