-- ==========================================================
-- DATABASE: db_futstatpro
-- Aplikasi Manajemen Statistik & Performa Tim Futsal (FUTSTATPRO)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `db_futstatpro` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_futstatpro`;

-- 1. Tabel Users (Manajemen Pengguna)
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('ADMIN','COACH','STAFF','MEDICAL') DEFAULT 'ADMIN',
  `status` enum('AKTIF','NONAKTIF') DEFAULT 'AKTIF',
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`username`, `password`, `role`, `status`) VALUES
('admin', 'admin123', 'ADMIN', 'AKTIF');

-- 2. Tabel Team Settings (Pengaturan Tim)
DROP TABLE IF EXISTS `team_settings`;
CREATE TABLE `team_settings` (
  `id_setting` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tim` varchar(100) DEFAULT 'FUTSALPRO',
  `slogan_tim` varchar(150) DEFAULT 'ELITE PERFORMANCE TEAM',
  `tahun_berdiri` int(4) DEFAULT 2024,
  `logo_url` longtext DEFAULT NULL,
  PRIMARY KEY (`id_setting`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `team_settings` (`id_setting`, `nama_tim`, `slogan_tim`, `tahun_berdiri`, `logo_url`) VALUES
(1, 'FUTSALPRO', 'ELITE PERFORMANCE TEAM', 2024, 'https://ui-avatars.com/api/?name=Futsal+Pro&background=2563eb&color=fff');

-- 3. Tabel Players (Data Pemain)
DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `id_player` bigint(20) NOT NULL,
  `nama_punggung` varchar(100) NOT NULL,
  `nama_pemain` varchar(150) DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `posisi` varchar(20) DEFAULT 'KIPER',
  `lapis` varchar(10) DEFAULT '1',
  `tinggi_cm` varchar(10) DEFAULT NULL,
  `berat_kg` varchar(10) DEFAULT NULL,
  `foto` longtext DEFAULT NULL,
  `physique_json` text DEFAULT NULL,
  `status_kondisi` enum('FIT','CEDERA','PEMULIHAN') DEFAULT 'FIT',
  PRIMARY KEY (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 4. Tabel Matches (Jadwal & Hasil Pertandingan)
DROP TABLE IF EXISTS `matches`;
CREATE TABLE `matches` (
  `id_match` bigint(20) NOT NULL,
  `tanggal_main` datetime NOT NULL,
  `lawan` varchar(100) NOT NULL,
  `jenis_laga` varchar(50) DEFAULT 'Sparring',
  `status_laga` enum('upcoming','completed') DEFAULT 'upcoming',
  `skor_tim` int(11) DEFAULT 0,
  `skor_lawan` int(11) DEFAULT 0,
  `catatan` text DEFAULT NULL,
  PRIMARY KEY (`id_match`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Tabel Player Stats (Statistik Pemain per Pertandingan)
DROP TABLE IF EXISTS `player_stats`;
CREATE TABLE `player_stats` (
  `id_stat` int(11) NOT NULL AUTO_INCREMENT,
  `id_match` bigint(20) NOT NULL,
  `id_player` bigint(20) NOT NULL,
  `is_playing` tinyint(1) DEFAULT 0,
  `rating` decimal(4,1) DEFAULT 5.0,
  `gol` int(11) DEFAULT 0,
  `shots_on_target` int(11) DEFAULT 0,
  `shots_off_target` int(11) DEFAULT 0,
  `assist` int(11) DEFAULT 0,
  `intercepts` int(11) DEFAULT 0,
  `saves` int(11) DEFAULT 0,
  `fouls` int(11) DEFAULT 0,
  `kartu_kuning` int(11) DEFAULT 0,
  `kartu_merah` int(11) DEFAULT 0,
  PRIMARY KEY (`id_stat`),
  KEY `idx_match` (`id_match`),
  KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Tabel Attendance (Kehadiran / Absensi Latihan & Pertandingan)
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id_absen` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` bigint(20) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_sesi` varchar(50) DEFAULT 'Latihan',
  `judul_sesi` varchar(150) DEFAULT NULL,
  `catatan_sesi` text DEFAULT NULL,
  `id_player` bigint(20) NOT NULL DEFAULT 0,
  `status_hadir` enum('Hadir','Izin','Sakit','Alpa') DEFAULT 'Alpa',
  PRIMARY KEY (`id_absen`),
  KEY `idx_sesi` (`id_sesi`),
  KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Tabel Fitness Tests (Tes Fisik & Teknik Pemain)
DROP TABLE IF EXISTS `fitness_tests`;
CREATE TABLE `fitness_tests` (
  `id_test` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi_tes` bigint(20) NOT NULL,
  `tanggal_tes` date NOT NULL,
  `jenis_tes` varchar(50) DEFAULT 'Fisik',
  `judul_tes` varchar(150) DEFAULT NULL,
  `id_player` bigint(20) NOT NULL DEFAULT 0,
  `endurance` decimal(5,2) DEFAULT NULL,
  `strength` decimal(5,2) DEFAULT NULL,
  `agility` decimal(5,2) DEFAULT NULL,
  `speed` decimal(5,2) DEFAULT NULL,
  `power` decimal(5,2) DEFAULT NULL,
  `dribbling` decimal(5,2) DEFAULT NULL,
  `passing` decimal(5,2) DEFAULT NULL,
  `shooting` decimal(5,2) DEFAULT NULL,
  `ball_control` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_test`),
  KEY `idx_sesi_tes` (`id_sesi_tes`),
  KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Tabel Medical Records (Medical Room / Data Cedera & Pemulihan)
DROP TABLE IF EXISTS `medical_records`;
CREATE TABLE `medical_records` (
  `id_medis` bigint(20) NOT NULL,
  `id_player` bigint(20) NOT NULL,
  `jenis_cedera` varchar(150) NOT NULL,
  `tgl_cedera` date NOT NULL,
  `estimasi_pulih` datetime NOT NULL,
  `catatan_fisio` text DEFAULT NULL,
  `created_at` bigint(20) DEFAULT NULL,
  `status_medis` enum('RAWAT','PULIH') DEFAULT 'RAWAT',
  PRIMARY KEY (`id_medis`),
  KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

