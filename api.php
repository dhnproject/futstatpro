<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = '127.0.0.1';
$port = 3306;
$dbname = 'db_futstatpro';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Pastikan database ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $pdo->exec("USE `$dbname`");

    // Pastikan struktur tabel sesuai
    ensureSchema($pdo);

    $action = $_GET['action'] ?? '';
    $rawBody = file_get_contents('php://input');
    $jsonBody = $rawBody ? json_decode($rawBody, true) : null;
    if (!$action && is_array($jsonBody) && isset($jsonBody['action'])) {
        $action = $jsonBody['action'];
    }

    if ($action === 'loadData' || $_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = loadAllData($pdo);
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        exit;
    }

    if ($action === 'saveData' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $payloadData = $jsonBody['data'] ?? null;
        if (!is_array($payloadData)) {
            throw new Exception('Data payload tidak valid.');
        }
        saveAllData($pdo, $payloadData);
        echo json_encode([
            'status' => 'success',
            'message' => 'Data berhasil disimpan ke database MySQL (db_futstatpro).'
        ]);
        exit;
    }

    echo json_encode(['error' => 'Action tidak dikenali.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

function ensureSchema(PDO $pdo) {
    // Cek apakah kolom nama_punggung sudah ada di tabel players. Jika belum (masih skema lama), perbarui struktur tabel.
    $stmt = $pdo->query("SHOW TABLES LIKE 'players'");
    $needMigration = false;
    if ($stmt->rowCount() === 0) {
        $needMigration = true;
    } else {
        $colCheck = $pdo->query("SHOW COLUMNS FROM `players` LIKE 'nama_punggung'");
        if ($colCheck->rowCount() === 0) {
            $needMigration = true;
        }
    }

    if ($needMigration) {
        $sqlFile = __DIR__ . '/db_futstatpro.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
    }
}

function loadAllData(PDO $pdo) {
    // 1. Users
    $stmt = $pdo->query("SELECT username, password, role, status FROM users ORDER BY id_user ASC");
    $appUsers = $stmt->fetchAll();
    if (empty($appUsers)) {
        $appUsers = [['username' => 'admin', 'password' => 'admin123', 'role' => 'ADMIN', 'status' => 'AKTIF']];
    }

    // 2. Team Settings
    $stmt = $pdo->query("SELECT nama_tim, slogan_tim, tahun_berdiri, logo_url FROM team_settings ORDER BY id_setting ASC LIMIT 1");
    $teamRow = $stmt->fetch();
    $teamSettings = [
        'name' => $teamRow['nama_tim'] ?? 'FUTSALPRO',
        'slogan' => $teamRow['slogan_tim'] ?? 'ELITE PERFORMANCE TEAM',
        'established' => isset($teamRow['tahun_berdiri']) ? (int)$teamRow['tahun_berdiri'] : 2024,
        'logo' => !empty($teamRow['logo_url']) ? $teamRow['logo_url'] : 'https://ui-avatars.com/api/?name=Futsal+Pro&background=2563eb&color=fff'
    ];

    // 3. Players
    $stmt = $pdo->query("SELECT * FROM players ORDER BY id_player ASC");
    $playerRows = $stmt->fetchAll();
    $players = [];
    foreach ($playerRows as $r) {
        $physique = !empty($r['physique_json']) ? json_decode($r['physique_json'], true) : new stdClass();
        $players[] = [
            'id' => (int)$r['id_player'],
            'nickname' => $r['nama_punggung'],
            'name' => $r['nama_pemain'] ?? '',
            'birthdate' => $r['tgl_lahir'] ?? '',
            'posisi' => $r['posisi'] ?? 'KIPER',
            'lapis' => $r['lapis'] ?? '1',
            'h' => $r['tinggi_cm'] ?? '',
            'w' => $r['berat_kg'] ?? '',
            'photo' => $r['foto'] ?: null,
            'physique' => $physique,
            'stats' => [
                'goals' => 0, 'shotOnTarget' => 0, 'shotOffTarget' => 0,
                'assists' => 0, 'intercepts' => 0, 'saves' => 0,
                'fouls' => 0, 'yellowCards' => 0, 'redCards' => 0,
                'matchesPlayed' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0,
                'mvpCount' => 0, 'avgRating' => '0.0'
            ]
        ];
    }

    // 4. Matches & Player Stats
    $stmt = $pdo->query("SELECT * FROM matches ORDER BY tanggal_main ASC, id_match ASC");
    $matchRows = $stmt->fetchAll();

    $stmtStats = $pdo->query("SELECT * FROM player_stats ORDER BY id_stat ASC");
    $allStats = $stmtStats->fetchAll();
    $statsByMatch = [];
    foreach ($allStats as $st) {
        $mId = (int)$st['id_match'];
        if (!isset($statsByMatch[$mId])) {
            $statsByMatch[$mId] = [];
        }
        $statsByMatch[$mId][] = [
            'pid' => (int)$st['id_player'],
            'isPlaying' => (bool)$st['is_playing'],
            'rating' => (float)$st['rating'],
            'goals' => (int)$st['gol'],
            'shotOnTarget' => (int)$st['shots_on_target'],
            'shotOffTarget' => (int)$st['shots_off_target'],
            'assists' => (int)$st['assist'],
            'intercepts' => (int)$st['intercepts'],
            'saves' => (int)$st['saves'],
            'fouls' => (int)$st['fouls'],
            'yellowCards' => (int)$st['kartu_kuning'],
            'redCards' => (int)$st['kartu_merah']
        ];
    }

    $matches = [];
    foreach ($matchRows as $m) {
        $mId = (int)$m['id_match'];
        // Format tanggal_main kembali ke YYYY-MM-DDTHH:mm jika perlu
        $dt = str_replace(' ', 'T', substr($m['tanggal_main'], 0, 16));
        $matches[] = [
            'id' => $mId,
            'opponent' => $m['lawan'],
            'date' => $dt,
            'type' => $m['jenis_laga'] ?? 'Sparring',
            'status' => $m['status_laga'] ?? 'upcoming',
            'scoreUs' => (int)$m['skor_tim'],
            'scoreThem' => (int)$m['skor_lawan'],
            'notes' => $m['catatan'] ?? '',
            'participation' => $statsByMatch[$mId] ?? []
        ];
    }

    // 5. Attendance
    $stmt = $pdo->query("SELECT * FROM attendance ORDER BY id_absen ASC");
    $attRows = $stmt->fetchAll();
    $attGroups = [];
    foreach ($attRows as $row) {
        $sId = (int)$row['id_sesi'];
        if (!isset($attGroups[$sId])) {
            $attGroups[$sId] = [
                'id' => $sId,
                'date' => $row['tanggal'],
                'type' => $row['jenis_sesi'] ?? 'Latihan',
                'title' => $row['judul_sesi'] ?? '',
                'notes' => $row['catatan_sesi'] ?? '',
                'records' => []
            ];
        }
        if ((int)$row['id_player'] > 0) {
            $attGroups[$sId]['records'][] = [
                'pid' => (int)$row['id_player'],
                'status' => $row['status_hadir'] ?? 'Alpa'
            ];
        }
    }
    $attendances = array_values($attGroups);

    // 6. Fitness Tests
    $stmt = $pdo->query("SELECT * FROM fitness_tests ORDER BY id_test ASC");
    $testRows = $stmt->fetchAll();
    $testGroups = [];
    foreach ($testRows as $row) {
        $tId = (int)$row['id_sesi_tes'];
        if (!isset($testGroups[$tId])) {
            $testGroups[$tId] = [
                'id' => $tId,
                'date' => $row['tanggal_tes'],
                'type' => $row['jenis_tes'] ?? 'Fisik',
                'title' => $row['judul_tes'] ?? '',
                'records' => []
            ];
        }
        if ((int)$row['id_player'] > 0) {
            $testGroups[$tId]['records'][] = [
                'pid' => (int)$row['id_player'],
                'endurance' => $row['endurance'] !== null ? (float)$row['endurance'] : null,
                'strength' => $row['strength'] !== null ? (float)$row['strength'] : null,
                'agility' => $row['agility'] !== null ? (float)$row['agility'] : null,
                'speed' => $row['speed'] !== null ? (float)$row['speed'] : null,
                'power' => $row['power'] !== null ? (float)$row['power'] : null,
                'dribbling' => $row['dribbling'] !== null ? (float)$row['dribbling'] : null,
                'passing' => $row['passing'] !== null ? (float)$row['passing'] : null,
                'shooting' => $row['shooting'] !== null ? (float)$row['shooting'] : null,
                'ballControl' => $row['ball_control'] !== null ? (float)$row['ball_control'] : null
            ];
        }
    }
    $tests = array_values($testGroups);

    // 7. Medical Records
    $stmt = $pdo->query("SELECT * FROM medical_records WHERE status_medis = 'RAWAT' ORDER BY estimasi_pulih ASC");
    $medRows = $stmt->fetchAll();
    $medicalRecords = [];
    foreach ($medRows as $row) {
        $est = str_replace(' ', 'T', substr($row['estimasi_pulih'], 0, 16));
        $medicalRecords[] = [
            'id' => (int)$row['id_medis'],
            'pid' => (int)$row['id_player'],
            'injuryType' => $row['jenis_cedera'],
            'incidentDate' => $row['tgl_cedera'],
            'estimatedReturn' => $est,
            'physioNotes' => $row['catatan_fisio'] ?? '',
            'createdAt' => $row['created_at'] ? (int)$row['created_at'] : time() * 1000
        ];
    }

    return [
        'appUsers' => $appUsers,
        'teamSettings' => $teamSettings,
        'players' => $players,
        'matches' => $matches,
        'attendances' => $attendances,
        'tests' => $tests,
        'medicalRecords' => $medicalRecords
    ];
}

function saveAllData(PDO $pdo, array $data) {
    $pdo->beginTransaction();
    try {
        // 1. Users
        if (isset($data['appUsers']) && is_array($data['appUsers'])) {
            $pdo->exec("DELETE FROM users");
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, role, status) VALUES (?, ?, ?, ?)");
            foreach ($data['appUsers'] as $u) {
                if (empty($u['username'])) continue;
                $stmtUser->execute([
                    $u['username'],
                    $u['password'] ?? 'admin123',
                    $u['role'] ?? 'ADMIN',
                    $u['status'] ?? 'AKTIF'
                ]);
            }
        }

        // 2. Team Settings
        if (isset($data['teamSettings']) && is_array($data['teamSettings'])) {
            $ts = $data['teamSettings'];
            $pdo->exec("DELETE FROM team_settings");
            $stmtTeam = $pdo->prepare("INSERT INTO team_settings (id_setting, nama_tim, slogan_tim, tahun_berdiri, logo_url) VALUES (1, ?, ?, ?, ?)");
            $stmtTeam->execute([
                $ts['name'] ?? 'FUTSALPRO',
                $ts['slogan'] ?? 'ELITE PERFORMANCE TEAM',
                !empty($ts['established']) ? (int)$ts['established'] : 2024,
                $ts['logo'] ?? null
            ]);
        }

        // Kumpulkan ID pemain yang sedang cedera untuk status_kondisi
        $injuredPids = [];
        if (isset($data['medicalRecords']) && is_array($data['medicalRecords'])) {
            foreach ($data['medicalRecords'] as $mr) {
                if (!empty($mr['pid'])) {
                    $injuredPids[(int)$mr['pid']] = true;
                }
            }
        }

        // 3. Players
        if (isset($data['players']) && is_array($data['players'])) {
            $pdo->exec("DELETE FROM players");
            $stmtPlayer = $pdo->prepare("
                INSERT INTO players (id_player, nama_punggung, nama_pemain, tgl_lahir, posisi, lapis, tinggi_cm, berat_kg, foto, physique_json, status_kondisi)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($data['players'] as $p) {
                $pid = (int)($p['id'] ?? round(microtime(true) * 1000));
                $tglLahir = !empty($p['birthdate']) ? $p['birthdate'] : null;
                $physJson = !empty($p['physique']) ? json_encode($p['physique']) : null;
                $statusKondisi = isset($injuredPids[$pid]) ? 'CEDERA' : 'FIT';

                $stmtPlayer->execute([
                    $pid,
                    $p['nickname'] ?? 'PEMAIN',
                    $p['name'] ?? '',
                    $tglLahir,
                    $p['posisi'] ?? 'KIPER',
                    $p['lapis'] ?? '1',
                    $p['h'] ?? '',
                    $p['w'] ?? '',
                    $p['photo'] ?? null,
                    $physJson,
                    $statusKondisi
                ]);
            }
        }

        // 4. Matches & 5. Player Stats
        if (isset($data['matches']) && is_array($data['matches'])) {
            $pdo->exec("DELETE FROM player_stats");
            $pdo->exec("DELETE FROM matches");

            $stmtMatch = $pdo->prepare("
                INSERT INTO matches (id_match, tanggal_main, lawan, jenis_laga, status_laga, skor_tim, skor_lawan, catatan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtStat = $pdo->prepare("
                INSERT INTO player_stats (id_match, id_player, is_playing, rating, gol, shots_on_target, shots_off_target, assist, intercepts, saves, fouls, kartu_kuning, kartu_merah)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($data['matches'] as $m) {
                $mId = (int)($m['id'] ?? round(microtime(true) * 1000));
                $dt = !empty($m['date']) ? str_replace('T', ' ', $m['date']) : date('Y-m-d H:i:s');
                if (strlen($dt) === 16) $dt .= ':00';

                $stmtMatch->execute([
                    $mId,
                    $dt,
                    $m['opponent'] ?? 'LAWAN',
                    $m['type'] ?? 'Sparring',
                    $m['status'] ?? 'upcoming',
                    (int)($m['scoreUs'] ?? 0),
                    (int)($m['scoreThem'] ?? 0),
                    $m['notes'] ?? ''
                ]);

                if (!empty($m['participation']) && is_array($m['participation'])) {
                    foreach ($m['participation'] as $part) {
                        $sot = isset($part['shotOnTarget']) ? (int)$part['shotOnTarget'] : (int)($part['shoots'] ?? 0);
                        $stmtStat->execute([
                            $mId,
                            (int)($part['pid'] ?? 0),
                            !empty($part['isPlaying']) ? 1 : 0,
                            (float)($part['rating'] ?? 5.0),
                            (int)($part['goals'] ?? 0),
                            $sot,
                            (int)($part['shotOffTarget'] ?? 0),
                            (int)($part['assists'] ?? 0),
                            (int)($part['intercepts'] ?? 0),
                            (int)($part['saves'] ?? 0),
                            (int)($part['fouls'] ?? 0),
                            (int)($part['yellowCards'] ?? 0),
                            (int)($part['redCards'] ?? 0)
                        ]);
                    }
                }
            }
        }

        // 6. Attendance
        if (isset($data['attendances']) && is_array($data['attendances'])) {
            $pdo->exec("DELETE FROM attendance");
            $stmtAtt = $pdo->prepare("
                INSERT INTO attendance (id_sesi, tanggal, jenis_sesi, judul_sesi, catatan_sesi, id_player, status_hadir)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($data['attendances'] as $att) {
                $sId = (int)($att['id'] ?? round(microtime(true) * 1000));
                $tgl = !empty($att['date']) ? $att['date'] : date('Y-m-d');
                $jenis = $att['type'] ?? 'Latihan';
                $judul = $att['title'] ?? '';
                $catatan = $att['notes'] ?? '';

                if (!empty($att['records']) && is_array($att['records'])) {
                    foreach ($att['records'] as $rec) {
                        $stmtAtt->execute([
                            $sId,
                            $tgl,
                            $jenis,
                            $judul,
                            $catatan,
                            (int)($rec['pid'] ?? 0),
                            $rec['status'] ?? 'Alpa'
                        ]);
                    }
                } else {
                    $stmtAtt->execute([$sId, $tgl, $jenis, $judul, $catatan, 0, 'Alpa']);
                }
            }
        }

        // 7. Fitness Tests
        if (isset($data['tests']) && is_array($data['tests'])) {
            $pdo->exec("DELETE FROM fitness_tests");
            $stmtTest = $pdo->prepare("
                INSERT INTO fitness_tests (id_sesi_tes, tanggal_tes, jenis_tes, judul_tes, id_player, endurance, strength, agility, speed, power, dribbling, passing, shooting, ball_control)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $numOrNull = function ($v) {
                return ($v === null || $v === '' || !is_numeric($v)) ? null : (float)$v;
            };
            foreach ($data['tests'] as $t) {
                $tId = (int)($t['id'] ?? round(microtime(true) * 1000));
                $tgl = !empty($t['date']) ? $t['date'] : date('Y-m-d');
                $jenis = $t['type'] ?? 'Fisik';
                $judul = $t['title'] ?? '';

                if (!empty($t['records']) && is_array($t['records'])) {
                    foreach ($t['records'] as $rec) {
                        $stmtTest->execute([
                            $tId,
                            $tgl,
                            $jenis,
                            $judul,
                            (int)($rec['pid'] ?? 0),
                            $numOrNull($rec['endurance'] ?? null),
                            $numOrNull($rec['strength'] ?? null),
                            $numOrNull($rec['agility'] ?? null),
                            $numOrNull($rec['speed'] ?? null),
                            $numOrNull($rec['power'] ?? null),
                            $numOrNull($rec['dribbling'] ?? null),
                            $numOrNull($rec['passing'] ?? null),
                            $numOrNull($rec['shooting'] ?? null),
                            $numOrNull($rec['ballControl'] ?? null)
                        ]);
                    }
                } else {
                    $stmtTest->execute([$tId, $tgl, $jenis, $judul, 0, null, null, null, null, null, null, null, null, null]);
                }
            }
        }

        // 8. Medical Records
        if (isset($data['medicalRecords']) && is_array($data['medicalRecords'])) {
            $pdo->exec("DELETE FROM medical_records");
            $stmtMed = $pdo->prepare("
                INSERT INTO medical_records (id_medis, id_player, jenis_cedera, tgl_cedera, estimasi_pulih, catatan_fisio, created_at, status_medis)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'RAWAT')
            ");
            foreach ($data['medicalRecords'] as $mr) {
                $medId = (int)($mr['id'] ?? round(microtime(true) * 1000));
                $est = !empty($mr['estimatedReturn']) ? str_replace('T', ' ', $mr['estimatedReturn']) : date('Y-m-d H:i:s');
                if (strlen($est) === 16) $est .= ':00';

                $stmtMed->execute([
                    $medId,
                    (int)($mr['pid'] ?? 0),
                    $mr['injuryType'] ?? '',
                    !empty($mr['incidentDate']) ? $mr['incidentDate'] : date('Y-m-d'),
                    $est,
                    $mr['physioNotes'] ?? '',
                    !empty($mr['createdAt']) ? (int)$mr['createdAt'] : round(microtime(true) * 1000)
                ]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

