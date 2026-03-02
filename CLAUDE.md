# MantisBT ProcessEngine Plugin

## Genel Bilgiler
MantisBT 2.24.2 (Schema 210) üzerinde çalışan bir "Süreç Motoru" eklentisi. Departmanlar arası fiyat talebi iş akışlarını otomatize eder. Departman listesi dinamik olarak yapılandırma sayfasından yönetilir (hardcode değildir).

## Dil Kuralı
- **Tüm çıktılar, commit mesajları, açıklamalar ve yorumlar her zaman Türkçe olmalıdır.**
- Kod içi değişken/fonksiyon adları İngilizce kalabilir ancak kullanıcıya dönük her şey Türkçe olacaktır.

## Teknik Ortam
- **MantisBT**: 2.24.2 (Schema 210) — Tüm kod bu sürümle uyumlu olmalı
- **PHP**: 7.x (MantisBT 2.24.x Docker imajı ile gelen sürüm)
- **Veritabanı**: MySQL 8.0
- **Docker**: MantisBT + MySQL + MailHog geliştirme ortamı
- **Plugin API**: MantisBT 2.x plugin API (MantisPlugin sınıfı)

## Proje Yapısı
```
├── docker-compose.yml              # Docker geliştirme ortamı
├── docker/mantis-config/           # MantisBT yapılandırma dosyaları
│   └── config_inc.php
├── plugins/ProcessEngine/          # Ana eklenti (Docker'a mount edilir)
│   ├── ProcessEngine.php           # Ana plugin sınıfı (schema, hook, config, event)
│   ├── pages/                      # Sayfa dosyaları
│   │   ├── dashboard.php           # Süreç Paneli (Faz 2)
│   │   ├── flow_designer.php       # Akış Tasarımcısı (Faz 3)
│   │   ├── flow_save.php           # AJAX kaydetme endpoint
│   │   ├── flow_validate.php       # AJAX doğrulama endpoint
│   │   ├── flow_publish.php        # AJAX yayınlama endpoint
│   │   ├── sla_check.php           # SLA kontrol sayfası (Faz 4)
│   │   ├── escalation_trigger.php  # Darboğaz görüntüleme (Faz 4)
│   │   ├── config_page.php         # Yapılandırma sayfası (Faz 4)
│   │   └── config_update.php       # Yapılandırma kaydetme
│   ├── core/                       # İş mantığı API'leri
│   │   ├── process_api.php         # Süreç loglama ve sorgu (Faz 1)
│   │   ├── flow_api.php            # Akış CRUD + graf doğrulama (Faz 3)
│   │   ├── sla_api.php             # SLA hesaplama motoru (Faz 4)
│   │   ├── escalation_api.php      # Eskalasyon kuralları (Faz 4)
│   │   └── notification_api.php    # E-posta bildirim şablonları (Faz 4)
│   ├── files/                      # CSS/JS varlıkları
│   │   ├── process_panel.css       # Dashboard stilleri
│   │   ├── process_panel.js        # Dashboard JS
│   │   ├── flow_designer.css       # Tasarımcı stilleri
│   │   ├── flow_designer.js        # SVG render, sürükle-bırak, AJAX
│   │   └── sla_dashboard.js        # SLA izleme JS
│   ├── lang/                       # Dil dosyaları
│   │   ├── strings_turkish.txt     # Türkçe (birincil)
│   │   └── strings_english.txt     # İngilizce (yedek)
│   └── db/
│       └── seed_data.php           # Varsayılan 2 akış örneği
├── scripts/
│   └── sla_cron.php                # Harici SLA cron betiği
├── docs/
│   └── Kullanim_Kilavuzu.md        # Kullanım kılavuzu (Türkçe)
├── CLAUDE.md                       # Bu dosya
├── .gitignore
└── Yerel_Gelistirme_Rehberi_v1.docx
```

## Veritabanı Tabloları
Tüm tablo adları `mantis_plugin_ProcessEngine_` ön ekiyle başlar:

| Tablo | Açıklama |
|-------|----------|
| `flow_definition_table` | Akış tanımları (ad, açıklama, durum, proje) |
| `step_table` | Akış adımları (departman, SLA, MantisBT durumu, konum, handler_id) |
| `transition_table` | Adımlar arası geçişler (koşullu/koşulsuz) |
| `log_table` | Süreç logu (bug_id, durum değişiklikleri, kullanıcı) |
| `sla_tracking_table` | SLA takibi (deadline, uyarı, aşım, eskalasyon) |

## Temel Kurallar ve Konvansiyonlar
- DB tablo adları: `plugin_table('isim')` yardımcısı ile oluşturulur
- Dil stringleri: `plugin_lang_get('string_adi')` ile çağrılır, `$s_plugin_ProcessEngine_` öneki kullanılır
- Sayfalar: `layout_page_header()` → `layout_page_begin()` → içerik → `layout_page_end()` kalıbı
- CSRF koruması: `form_security_field()` / `form_security_validate()` / `form_security_purge()`
- AJAX endpoint'leri: JSON giriş (`php://input`) + JSON çıkış (`Content-Type: application/json`)
- Parametreli sorgular: `db_param_push()` + `db_param()` kullanılır (SQL injection koruması)

## KRİTİK: MantisBT Çekirdek İşlevleri Asla Bozulmamalı
Standart MantisBT işlemleri (sorun oluşturma, durum değiştirme, atama, not ekleme vb.) **HİÇBİR KOŞULDA** bozulmamalıdır. Bu kural en yüksek önceliklidir.

**Event hook kuralları:**
- `EVENT_UPDATE_BUG` hook'u içinde **asla** `bug_set_field()`, `bugnote_add()` veya bug cache'ini bozan fonksiyonlar çağrılmamalıdır — MantisBT'nin iç cache mekanizmasını bozar
- Hook içinde DB güncellemesi gerekiyorsa `register_shutdown_function()` ile ertelenmelidir
- `EVENT_REPORT_BUG` hook'unda `$p_bug_data` özellikleri güvenle değiştirilebilir (bug henüz DB'ye yazılmamıştır)
- Schema ile eklenen yeni sütunlara erişimde **her zaman** `isset()` kontrolü yapılmalıdır (schema henüz uygulanmamış olabilir)
- Kullanıcı ID referanslarında **her zaman** `user_exists()` kontrolü yapılmalıdır

## Akış Durumları
| Kod | Durum | Açıklama |
|-----|-------|----------|
| 0 | TASLAK | Yeni oluşturulmuş, düzenlenebilir |
| 1 | ONAY_BEKLIYOR | Yayın için onay bekliyor |
| 2 | AKTİF | Canlıda çalışıyor, değişiklik yapılamaz |

## SLA Eskalasyon Seviyeleri
| Seviye | Koşul | Aksiyon |
|--------|-------|---------|
| UYARI (Sarı) | SLA süresinin %80'i doldu | Atanan kullanıcıya e-posta |
| AŞIM (Kırmızı) | SLA süresi doldu | Atanan + departman yöneticisine e-posta |
| Eskalasyon Lv1 | SLA'nın 1.5x'i | MANAGER rolüne e-posta |
| Eskalasyon Lv2 | SLA'nın 2x'i | ADMINISTRATOR'e e-posta |

## Yapılandırma Varsayılanları
```php
'manage_threshold'     => MANAGER,      // Yönetim erişim seviyesi
'view_threshold'       => REPORTER,     // Görüntüleme erişim seviyesi
'sla_warning_percent'  => 80,           // SLA uyarı yüzdesi
'business_hours_start' => 9,            // İş saati başlangıç
'business_hours_end'   => 18,           // İş saati bitiş
'working_days'         => '1,2,3,4,5',  // Çalışma günleri (1=Pzt, 7=Paz)
'departments'          => '',           // Virgülle ayrılmış departman adları (boş = serbest giriş)
```

## Docker Komutları
```bash
docker compose up -d                    # Ortamı başlat
docker compose down                     # Ortamı durdur
docker compose logs mantisbt            # MantisBT loglarını görüntüle
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e "SHOW TABLES LIKE 'mantis_plugin_ProcessEngine_%';"  # Tabloları kontrol et
docker exec mantisbt php /var/www/html/scripts/sla_cron.php  # SLA cron çalıştır
```

## URL'ler
- **MantisBT**: http://localhost:8080
- **MailHog**: http://localhost:8025

## Git
- **Repo**: https://github.com/VerhexIO/mantis.process.engine.git
- **Commit mesajları Türkçe yazılmalıdır**

## Departman Yönetimi
Departman listesi dinamik olarak iki kaynaktan toplanır:
- `departments` config anahtarı (virgülle ayrılmış, yapılandırma sayfasından yönetilir)
- `step_table`'daki mevcut DISTINCT department değerleri

`process_get_departments()` fonksiyonu (`core/process_api.php`) her iki kaynağı birleştirip sıralı döndürür. Akış tasarımcısında "Diğer" seçeneği ile serbest metin girişi de mümkündür.

## Kısıtlamalar
- **Çoklu Sorun Takibi DESTEKLENMİYOR:** Plugin "Tek Sorun, Çoklu Adım" modeli ile çalışır. Bir sorun tek bir akışta ilerler. Farklı sorunları birbirine bağlayan `parent_bug_id`, `process_group_id` gibi bir alan yoktur.
- MantisBT'nin native `bug_relationship_table`'ı süreç bağlamında kullanılmamaktadır.

## KRİTİK: MantisBT Çekirdek İşlevleri Asla Bozulmamalı
- Standart MantisBT komutları (durum değiştir, atama, sorun güncelle vs.) her zaman çalışmalı
- Plugin hook'ları MantisBT çekirdek fonksiyonlarını **asla bozmamalı**

### MantisBT Event Hook Parametre İmzaları
EVENT_UPDATE_BUG hook parametreleri dikkatli kullanılmalı:

```
# bug_update.php'de çağrılış:
event_signal( 'EVENT_UPDATE_BUG', array( $t_existing_bug, $t_updated_bug ) );

# Plugin callback'te:
on_bug_update( $p_event, $p_existing_bug, $p_updated_bug )
  - $p_existing_bug: BugData nesnesi (güncelleme ÖNCESİ)
  - $p_updated_bug:  BugData nesnesi (güncelleme SONRASI)
  - Her iki parametre de BugData nesnesidir, tamsayı DEĞİLDİR!
  - bug_get() veya bug_get_field() ÇAĞIRMAYIN — BugData nesnesinden doğrudan okuyun

# EVENT_REPORT_BUG ise farklıdır:
event_signal( 'EVENT_REPORT_BUG', array( $this->issue, $t_issue_id ) );
on_bug_report( $p_event, $p_bug_data, $p_bug_id )
  - $p_bug_data: BugData nesnesi
  - $p_bug_id: integer (sorun ID)
```

### Hook İçinden Asla Çağrılmaması Gerekenler
- `bug_set_field()` — bug cache'i bozar, "Illegal offset type" hatasına neden olur
- `bugnote_add()` — bug_clear_cache() çağırarak cache'i bozar
- `bug_get($non_integer)` — nesne ile çağrıldığında "Illegal offset type" hatası verir
- Hook içinden handler değiştirmek gerekiyorsa `register_shutdown_function()` ile ertelenmiş SQL UPDATE kullanılmalı
