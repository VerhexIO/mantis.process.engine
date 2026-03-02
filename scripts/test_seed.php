<?php
/**
 * ProcessEngine - Test Verisi Oluşturucu
 *
 * Docker içinde çalıştırılır:
 *   docker exec mantisbt php /var/www/html/plugins/ProcessEngine/../../scripts/test_seed.php
 * veya MantisBT kök dizininden:
 *   docker exec mantisbt php /var/www/html/scripts/test_seed.php
 *
 * Yapılanlar:
 *  1. Test kullanıcıları oluşturur (yoksa): satis_user, fiyat_user, satin_user, yonetici_user, operasyon_user
 *  2. "Fiyat Talebi" akışının adımlarına handler_id atar
 *  3. Farklı aşamalarda 6 test sorunu oluşturur ve süreç loglarını yazar
 *  4. SLA tracking kayıtları oluşturur
 */

// MantisBT bootstrap
$g_bypass_headers = true;
require_once( dirname( __FILE__ ) . '/../core.php' );
require_api( 'bug_api.php' );
require_api( 'user_api.php' );
require_api( 'bugnote_api.php' );
require_api( 'project_api.php' );

echo "=== ProcessEngine Test Verisi Oluşturucu ===\n\n";

// ──────────────────────────────────────────
// 1. Test Kullanıcıları
// ──────────────────────────────────────────
echo "--- 1. Test Kullanıcıları ---\n";

$test_users = array(
    'satis_user'     => array( 'realname' => 'Ahmet Satış',      'email' => 'satis@test.local',     'access' => REPORTER ),
    'fiyat_user'     => array( 'realname' => 'Ayşe Fiyatlandırma','email' => 'fiyat@test.local',     'access' => UPDATER ),
    'satin_user'     => array( 'realname' => 'Mehmet Satınalma', 'email' => 'satinalma@test.local', 'access' => UPDATER ),
    'yonetici_user'  => array( 'realname' => 'Fatma Yönetici',   'email' => 'yonetici@test.local',  'access' => MANAGER ),
    'operasyon_user' => array( 'realname' => 'Can Operasyon',    'email' => 'operasyon@test.local', 'access' => UPDATER ),
);

$user_ids = array();
foreach( $test_users as $username => $info ) {
    if( user_is_name_valid( $username ) && !user_is_name_unique( $username ) ) {
        $user_ids[$username] = user_get_id_by_realname( $info['realname'] );
        if( $user_ids[$username] === false ) {
            $user_ids[$username] = user_get_id_by_name( $username );
        }
        echo "  [MEVCUT] $username (ID: {$user_ids[$username]})\n";
    } else {
        $user_ids[$username] = user_create(
            $username,
            'Test1234!',
            $info['email'],
            $info['access'],
            false, // protected
            true,  // enabled
            $info['realname']
        );
        echo "  [YENİ] $username (ID: {$user_ids[$username]})\n";
    }
}

// ──────────────────────────────────────────
// 2. Proje kontrolü
// ──────────────────────────────────────────
echo "\n--- 2. Proje Kontrolü ---\n";

// İlk mevcut projeyi kullan veya oluştur
$all_projects = project_get_all_rows();
if( empty( $all_projects ) ) {
    $project_id = project_create( 'Test Projesi', 'ProcessEngine test projesi', VS_PUBLIC, true );
    echo "  [YENİ] 'Test Projesi' oluşturuldu (ID: $project_id)\n";
} else {
    $project_id = (int) $all_projects[0]['id'];
    echo "  [MEVCUT] Proje: " . $all_projects[0]['name'] . " (ID: $project_id)\n";
}

// Kullanıcıları projeye ekle
foreach( $user_ids as $username => $uid ) {
    if( !project_includes_user( $project_id, $uid ) ) {
        $access = $test_users[$username]['access'];
        project_add_user( $project_id, $uid, $access );
        echo "  Kullanıcı '$username' projeye eklendi (access: $access)\n";
    }
}

// ──────────────────────────────────────────
// 3. Aktif akışı bul ve handler_id ata
// ──────────────────────────────────────────
echo "\n--- 3. Akış ve Handler Atama ---\n";

$t_flow_table = db_get_table( 'plugin_ProcessEngine_flow_definition' );
$t_step_table = db_get_table( 'plugin_ProcessEngine_step' );
$t_log_table  = db_get_table( 'plugin_ProcessEngine_log' );
$t_sla_table  = db_get_table( 'plugin_ProcessEngine_sla_tracking' );

// Aktif akışı bul
db_param_push();
$result = db_query( "SELECT * FROM $t_flow_table WHERE status = 2 ORDER BY id ASC LIMIT 1" );
$flow = db_fetch_array( $result );

if( $flow === false ) {
    echo "  [HATA] Aktif akış bulunamadı! Önce seed_data çalıştırın veya akış yayınlayın.\n";
    exit(1);
}

$flow_id = (int) $flow['id'];
echo "  Aktif akış: '{$flow['name']}' (ID: $flow_id)\n";

// Adımları çek
db_param_push();
$result = db_query(
    "SELECT * FROM $t_step_table WHERE flow_id = " . db_param() . " ORDER BY step_order ASC",
    array( $flow_id )
);
$steps = array();
while( $row = db_fetch_array( $result ) ) {
    $steps[] = $row;
}
echo "  Adım sayısı: " . count( $steps ) . "\n";

// Her adıma handler_id ata (departmana göre)
$dept_handler_map = array(
    'Satış'           => $user_ids['satis_user'],
    'Fiyatlandırma'   => $user_ids['fiyat_user'],
    'Satınalma'       => $user_ids['satin_user'],
    'Yönetim'         => $user_ids['yonetici_user'],
    'Satış Operasyon' => $user_ids['operasyon_user'],
);

foreach( $steps as &$step ) {
    $handler = isset( $dept_handler_map[$step['department']] ) ? $dept_handler_map[$step['department']] : 0;
    if( $handler > 0 ) {
        db_param_push();
        db_query(
            "UPDATE $t_step_table SET handler_id = " . db_param() . " WHERE id = " . db_param(),
            array( $handler, (int) $step['id'] )
        );
        $step['handler_id'] = $handler;
        echo "  Adım '{$step['name']}' → handler_id: $handler\n";
    }
}
unset( $step );

// ──────────────────────────────────────────
// 4. Test sorunları oluştur
// ──────────────────────────────────────────
echo "\n--- 4. Test Sorunları ---\n";

// admin kullanıcı ID'si (sorun oluşturmak için)
$admin_id = user_get_id_by_name( 'administrator' );
if( $admin_id === false ) {
    $admin_id = 1; // varsayılan
}

// Auth'u admin olarak ayarla
auth_attempt_script_login( 'administrator' );

/**
 * Test sorunu oluştur ve süreç loglarını yaz
 *
 * @param int $project_id  Proje ID
 * @param string $summary  Sorun başlığı
 * @param int $flow_id     Akış ID
 * @param array $steps     Akış adımları
 * @param int $advance_to  Kaç adım ilerletilecek (0=sadece oluştur, 1=1. adım, 2=2. adıma kadar...)
 * @param int $reporter_id Raporlayan kullanıcı
 * @return int Bug ID
 */
function create_test_bug( $project_id, $summary, $flow_id, $steps, $advance_to, $reporter_id ) {
    global $t_log_table, $t_sla_table;

    // Sorun oluştur
    $bug_data = new BugData();
    $bug_data->project_id = $project_id;
    $bug_data->summary = $summary;
    $bug_data->description = 'ProcessEngine test verisi - otomatik oluşturuldu.';
    $bug_data->category_id = 1;
    $bug_data->reporter_id = $reporter_id;
    $bug_data->reproducibility = 70; // have not tried
    $bug_data->severity = 50; // minor
    $bug_data->priority = 30; // normal

    // İlk adımın durumunu ata
    if( !empty( $steps ) ) {
        $bug_data->status = (int) $steps[0]['mantis_status'];
        $handler = isset( $steps[0]['handler_id'] ) ? (int) $steps[0]['handler_id'] : 0;
        if( $handler > 0 ) {
            $bug_data->handler_id = $handler;
        }
    }

    $bug_id = bug_create( $bug_data );
    echo "  [BUG #$bug_id] '$summary'";

    // Başlangıç log kaydı
    $now = time();
    db_param_push();
    db_query(
        "INSERT INTO $t_log_table (bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at)
         VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
         . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
        array( $bug_id, $flow_id, (int) $steps[0]['id'], 0, (int) $steps[0]['mantis_status'],
               $reporter_id, 'Süreç başlatıldı', $now - ( $advance_to * 3600 * 8 ) )
    );

    // SLA kaydı (ilk adım)
    if( (int) $steps[0]['sla_hours'] > 0 ) {
        $started = $now - ( $advance_to * 3600 * 8 );
        $deadline = $started + ( (int) $steps[0]['sla_hours'] * 3600 );
        $completed = ( $advance_to > 0 ) ? $started + 3600 * 4 : null;
        db_param_push();
        db_query(
            "INSERT INTO $t_sla_table (bug_id, step_id, flow_id, sla_hours, started_at, deadline_at, completed_at, sla_status)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array( $bug_id, (int) $steps[0]['id'], $flow_id, (int) $steps[0]['sla_hours'],
                   $started, $deadline, $completed, 'NORMAL' )
        );
    }

    // Adımları ilerlet
    for( $i = 1; $i <= $advance_to && $i < count( $steps ); $i++ ) {
        $prev_step = $steps[$i - 1];
        $curr_step = $steps[$i];

        // Durum değiştir
        $old_status = (int) $prev_step['mantis_status'];
        $new_status = (int) $curr_step['mantis_status'];
        $log_time = $now - ( ( $advance_to - $i ) * 3600 * 8 );
        $changer_id = isset( $prev_step['handler_id'] ) && (int) $prev_step['handler_id'] > 0
            ? (int) $prev_step['handler_id'] : $reporter_id;

        // Bug durumunu güncelle
        bug_set_field( $bug_id, 'status', $new_status );
        $handler = isset( $curr_step['handler_id'] ) ? (int) $curr_step['handler_id'] : 0;
        if( $handler > 0 ) {
            bug_set_field( $bug_id, 'handler_id', $handler );
        }

        // Log kaydı
        db_param_push();
        db_query(
            "INSERT INTO $t_log_table (bug_id, flow_id, step_id, from_status, to_status, user_id, note, created_at)
             VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
             . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
            array( $bug_id, $flow_id, (int) $curr_step['id'], $old_status, $new_status,
                   $changer_id, '', $log_time )
        );

        // Önceki adım SLA tamamla
        db_param_push();
        db_query(
            "UPDATE $t_sla_table SET completed_at = " . db_param() . " WHERE bug_id = " . db_param()
            . " AND step_id = " . db_param() . " AND completed_at IS NULL",
            array( $log_time, $bug_id, (int) $prev_step['id'] )
        );

        // Yeni adım SLA başlat
        if( (int) $curr_step['sla_hours'] > 0 ) {
            $sla_started = $log_time;
            $sla_deadline = $sla_started + ( (int) $curr_step['sla_hours'] * 3600 );

            // Son adımdaysa tamamlanmamış bırak
            $sla_completed = null;
            $sla_status = 'NORMAL';

            // SLA durumu simülasyonu
            if( $i === $advance_to ) {
                // Mevcut adım — aktif
                $remaining = $sla_deadline - $now;
                if( $remaining < 0 ) {
                    $sla_status = 'EXCEEDED';
                } else if( $remaining < ( (int) $curr_step['sla_hours'] * 3600 * 0.2 ) ) {
                    $sla_status = 'WARNING';
                }
            } else {
                $sla_completed = $sla_started + 3600 * 4;
            }

            db_param_push();
            db_query(
                "INSERT INTO $t_sla_table (bug_id, step_id, flow_id, sla_hours, started_at, deadline_at, completed_at, sla_status)
                 VALUES (" . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ", "
                 . db_param() . ", " . db_param() . ", " . db_param() . ", " . db_param() . ")",
                array( $bug_id, (int) $curr_step['id'], $flow_id, (int) $curr_step['sla_hours'],
                       $sla_started, $sla_deadline, $sla_completed, $sla_status )
            );
        }
    }

    $current_step_idx = min( $advance_to, count( $steps ) - 1 );
    echo " → Adım " . ( $current_step_idx + 1 ) . "/" . count( $steps );
    echo " (" . $steps[$current_step_idx]['name'] . ")\n";

    return $bug_id;
}

// ── Test Sorunları ──

// Sorun 1: 1. adımda (Talep Oluşturma) — yeni oluşturulmuş
create_test_bug(
    $project_id,
    'FT-001: ABC Firması için çelik sac fiyat talebi',
    $flow_id, $steps, 0,
    $user_ids['satis_user']
);

// Sorun 2: 2. adımda (Fiyat Analizi) — ilerletilmiş
create_test_bug(
    $project_id,
    'FT-002: XYZ Ltd. alüminyum profil fiyatlandırma',
    $flow_id, $steps, 1,
    $user_ids['satis_user']
);

// Sorun 3: 3. adımda (Tedarikçi Kontrolü)
create_test_bug(
    $project_id,
    'FT-003: DEF A.Ş. bakır boru tedarik fiyatı',
    $flow_id, $steps, 2,
    $user_ids['satis_user']
);

// Sorun 4: 4. adımda (Yönetim Onayı) — SLA uyarı yakın
create_test_bug(
    $project_id,
    'FT-004: GHI Corp. paslanmaz çelik fiyat onayı',
    $flow_id, $steps, 3,
    $user_ids['satis_user']
);

// Sorun 5: 5. adımda (Teklif Hazırlama) — neredeyse tamamlanmış
create_test_bug(
    $project_id,
    'FT-005: JKL San. titanyum alaşım teklifi',
    $flow_id, $steps, 4,
    $user_ids['satis_user']
);

// Sorun 6: 2. adımda — SLA aşımı simülasyonu
$bug6_id = create_test_bug(
    $project_id,
    'FT-006: MNO Ltd. pirinç levha acil fiyat talebi',
    $flow_id, $steps, 1,
    $user_ids['satis_user']
);

// Bug 6'nın SLA'sını EXCEEDED yap (geçmişteki bir deadline ile)
db_param_push();
db_query(
    "UPDATE $t_sla_table SET sla_status = 'EXCEEDED', deadline_at = " . db_param()
    . " WHERE bug_id = " . db_param() . " AND completed_at IS NULL",
    array( time() - 7200, $bug6_id ) // 2 saat önce dolmuş
);

echo "\n--- 5. Özet ---\n";
echo "  Test kullanıcıları: " . count( $user_ids ) . " adet\n";
echo "  Proje: $project_id\n";
echo "  Akış: $flow_id (handler_id atandı)\n";
echo "  Test sorunları: 6 adet (farklı aşamalarda)\n";
echo "\n=== Test verisi başarıyla oluşturuldu! ===\n";
echo "\nDoğrulama komutları:\n";
echo "  docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e \"SELECT id, name, handler_id FROM mantis_plugin_ProcessEngine_step_table WHERE flow_id=$flow_id;\"\n";
echo "  docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e \"SELECT l.bug_id, l.from_status, l.to_status, s.name as step_name FROM mantis_plugin_ProcessEngine_log_table l LEFT JOIN mantis_plugin_ProcessEngine_step_table s ON l.step_id=s.id ORDER BY l.bug_id, l.id;\"\n";
echo "  docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e \"SELECT bug_id, sla_status, completed_at FROM mantis_plugin_ProcessEngine_sla_tracking_table ORDER BY id;\"\n";
