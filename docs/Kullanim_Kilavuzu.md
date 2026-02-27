# ProcessEngine Eklentisi - Detayli Kullanim Kilavuzu

> **Surum:** 1.0.0
> **Uyumluluk:** MantisBT 2.24.2 (Schema 210), PHP 7.x, MySQL 8.0
> **Gelistirici:** VerhexIO

---

## Icindekiler

1. [Genel Bakis](#1-genel-bakis)
2. [Kurulum](#2-kurulum)
3. [Ilk Yapilandirma](#3-ilk-yapilandirma)
4. [Surec Paneli (Dashboard)](#4-surec-paneli-dashboard)
5. [Akis Tasarimcisi (Flow Designer)](#5-akis-tasarimcisi-flow-designer)
6. [SLA Yonetimi](#6-sla-yonetimi)
7. [Eskalasyon Sistemi](#7-eskalasyon-sistemi)
8. [Surec Izleme ve Zaman Cizelgesi](#8-surec-izleme-ve-zaman-cizelgesi)
9. [Yapilandirma Sayfasi](#9-yapilandirma-sayfasi)
10. [Cron Gorevi (Otomatik SLA Kontrolu)](#10-cron-gorevi)
11. [Departmanlar ve Roller](#11-departmanlar-ve-roller)
12. [Veritabani Yapisi](#12-veritabani-yapisi)
13. [Sorun Giderme](#13-sorun-giderme)

---

## 1. Genel Bakis

ProcessEngine, MantisBT uzerinde calisan bir **surec motoru** eklentisidir. Departmanlar arasi is akislarini (fiyat talebi, urun gelistirme vb.) gorsel olarak tasarlamaniza, SLA surelerini takip etmenize ve darbogazlari tespit etmenize olanak tanir.

### Temel Yetenekler

| Ozellik | Aciklama |
|---------|----------|
| **Gorsel Akis Tasarimcisi** | Surukle-birak ile is akisi tasarlama (SVG tabanli) |
| **SLA Takibi** | Her adim icin saat bazinda SLA suresi tanimlama ve izleme |
| **Otomatik Eskalasyon** | SLA asiminda 4 seviyeli bildirim sistemi |
| **Surec Paneli** | Canli ozet kartlari ve filtrelenebilir talep tablosu |
| **Zaman Cizelgesi** | Her talep icin durum degisiklik gecmisi |
| **Darbogaz Analizi** | En cok SLA asimi yasanan adimlarin raporlanmasi |

### Desteklenen Departmanlar

Satis, Fiyatlandirma, Satis Operasyon, Satinalma, ArGe, Yonetim, Kalite

---

## 2. Kurulum

### On Kosullar

- MantisBT 2.24.2 kurulu ve calisiyor olmali
- MySQL 8.0 veritabani
- PHP 7.x

### Kurulum Adimlari

1. `plugins/ProcessEngine/` klasorunu MantisBT kurulum dizinine kopyalayin.

2. MantisBT'ye **administrator** olarak giris yapin.

3. **Yonetim > Eklentileri Yonet** sayfasina gidin.

4. "Available Plugins" bolumunde **Process Engine 1.0.0** satirindaki **Install** butonuna tiklayin.

5. Eklenti 5 veritabani tablosu olusturur:
   - `mantis_plugin_ProcessEngine_flow_definition_table`
   - `mantis_plugin_ProcessEngine_step_table`
   - `mantis_plugin_ProcessEngine_transition_table`
   - `mantis_plugin_ProcessEngine_log_table`
   - `mantis_plugin_ProcessEngine_sla_tracking_table`

6. Kurulum tamamlandiginda ust menude **"Surec Paneli"** baglantisi gorunur.

### Docker Ortami

```bash
docker compose up -d          # Ortami baslat
docker compose down            # Ortami durdur
docker compose logs mantisbt   # Loglari goruntule
```

- **MantisBT:** http://localhost:8080
- **MailHog (e-posta testi):** http://localhost:8025

---

## 3. Ilk Yapilandirma

Kurulumdan sonra **Yonetim > Surec Motoru Ayarlari** sayfasina gidin.

### Varsayilan Degerler

| Ayar | Varsayilan | Aciklama |
|------|-----------|----------|
| Yonetim Erisim Seviyesi | MANAGER | Akis tasarimi ve yapilandirma erisimi |
| Goruntuleme Erisim Seviyesi | REPORTER | Surec paneli ve zaman cizelgesi gorunturlugu |
| SLA Uyari Yuzdesi | %80 | Bu oran dolunca uyari e-postasi gonderilir |
| Is Saati Baslangic | 09:00 | SLA hesabi bu saatten baslar |
| Is Saati Bitis | 18:00 | SLA hesabi bu saatte durur |
| Calisma Gunleri | 1,2,3,4,5 | Pazartesi-Cuma (1=Pzt, 7=Paz) |

### Ornek Veri Yukleme

Yapilandirma sayfasinin alt bolumundeki **"Ornek Veri Yukle"** butonuna tiklayarak 2 hazir akis sablonu yukleyebilirsiniz. Bu, eklentiyi hizlica denemek icin kullanislidir.

---

## 4. Surec Paneli (Dashboard)

**Erisim:** Ust menu > **Surec Paneli**
**Gerekli Yetki:** Goruntuleme Erisim Seviyesi (varsayilan: REPORTER)

### Ozet Kartlari

Sayfanin ust kisminda 6 ozet karti bulunur:

| Kart | Renk | Aciklama |
|------|------|----------|
| Toplam Talep | Gri | Surec logu olan toplam benzersiz talep sayisi |
| Aktif Surecler | Mavi | SLA takibi devam eden talepler |
| SLA Asimi | Kirmizi | SLA suresi asilmis talepler |
| Ort. Cozum Suresi | Mor | Tamamlanan SLA kayitlarinin ortalama suresi (saat) |
| Bugun Guncellenen | Yesil | Bugun durum degisikligi olan talepler |
| Bekleyen Onaylar | Turuncu | Henuz cozulmemis (status < 80) talepler |

### Filtre Butonlari

Talep tablosu 4 filtre ile daraltilabilir:

- **Tumu** - Tum surecli talepler
- **Aktif** - Devam eden talepler (status < 80)
- **SLA Asimi** - SLA suresi asilmis talepler
- **Tamamlanan** - Cozulmus/kapatilmis talepler (status >= 80)

### Talep Tablosu

Her satir su bilgileri gosterir:

| Sutun | Aciklama |
|-------|----------|
| Talep No | Tiklanabilir baglanti (talep detay sayfasina yonlendirir) |
| Baslik | Talebin ozeti |
| Mevcut Adim | Surecte hangi adimda oldugu |
| Departman | Adimin ait oldugu departman |
| SLA Durumu | NORMAL (yesil), WARNING (sari), EXCEEDED (kirmizi) |
| Guncelleme | Son durum degisikligi tarihi |

---

## 5. Akis Tasarimcisi (Flow Designer)

**Erisim:** Surec Paneli > **Akis Tasarimcisi** veya Yonetim > **Surec Motoru Ayarlari** > **Akis Tasarimcisi**
**Gerekli Yetki:** Yonetim Erisim Seviyesi (varsayilan: MANAGER)

### Akis Listesi

Ilk acilista tum akislarin listesi goruntulenir:

| Sutun | Aciklama |
|-------|----------|
| ID | Akis tanimlayicisi |
| Akis Adi | Tiklanabilir (tasarimci acilir) |
| Durum | Taslak / Onay Bekliyor / Aktif |
| Guncelleme | Son degisiklik tarihi |
| Islemler | Duzenle / Sil butonlari |

**Yeni Akis** butonuna tiklayarak bos bir akis olusturabilirsiniz.

### Akis Durumlari

| Durum | Kod | Aciklama |
|-------|-----|----------|
| **TASLAK** | 0 | Yeni olusturulmus, serbestce duzenlenebilir |
| **ONAY BEKLIYOR** | 1 | Yayin icin onay asamasinda |
| **AKTIF** | 2 | Canli ortamda calisiyor, degisiklik yapilamaz |

### Gorsel Tasarimci Kullanimi

Bir akisa tikladiginda SVG tabanli gorsel tasarimci acilir:

#### Ust Bilgi Alani
- **Akis Adi:** Akisin gorunen adi (duzenlenebilir)
- **Aciklama:** Akis hakkinda kisa bilgi
- **Durum Etiketi:** Suanki akis durumu

#### Arac Cubugu

| Buton | Islem |
|-------|-------|
| **Adim Ekle** | Kanvasa yeni bir adim dugumleri ekler |
| **Kaydet** | Tum adimlari ve gecisleri sunucuya kaydeder (AJAX) |
| **Dogrula** | Akis grafigini dogrulama kontrolundan gecirir |
| **Yayinla** | Dogrulama basariliysa akisi AKTiF duruma gecirir |

#### Adim Ozellikleri

Her adim dgumlune cift tiklayarak duzenleyebilirsiniz:

| Ozellik | Aciklama |
|---------|----------|
| **Adim Adi** | Gosterim adi (orn: "Fiyat Talebi Girisi") |
| **Departman** | Sorumlu departman (Satis, Fiyatlandirma, vb.) |
| **SLA (Saat)** | Bu adim icin tanimlanan maksimum sure |
| **Rol** | Gerekli MantisBT rolu (reporter, developer, manager, vb.) |
| **MantisBT Durumu** | Bu adima karsilik gelen MantisBT issue durumu |

#### Gecisler (Transitions)

Adimlar arasinda ok cizerek gecis olusturabilirsiniz. Gecisler:
- **Kosulsuz:** Dogrudan bir adimdan digerine gecis
- **Kosullu:** Belirli bir alan ve deger kosuluna bagli gecis

### Akis Dogrulama Kurallari

**Dogrula** butonuna basildiginda su kontroller yapilir:

1. **Baslangic Dugumu:** Tam olarak 1 adet gelen gecisi olmayan adim olmali
2. **Bitis Dugumu:** En az 1 adet giden gecisi olmayan adim olmali
3. **Dongu Kontrolu:** Akista dongu (cycle) olmamali (DFS algoritmasi)
4. **Erisilebilirlik:** Tum adimlar baslangictan erisilebilir olmali (BFS algoritmasi)

Dogrulama basarisiz olursa hata mesajlari gosterilir:
- "Akista bir baslangic dugumu olmalidir."
- "Akista en az bir bitis dugumu olmalidir."
- "Akista dongu tespit edildi."
- "Bazi dugumlere baslangictan ulasilamiyor."

### Yayinlama

- Yayinlama oncesi dogrulama otomatik calisir
- Ayni projedeki onceki AKTiF akis otomatik olarak TASLAK durumuna doner
- Her projede ayni anda yalnizca 1 AKTiF akis bulunabilir

---

## 6. SLA Yonetimi

**Erisim:** Yapilandirma > **SLA Kontrol** veya Surec Paneli icerisinden
**Gerekli Yetki:** MANAGER

### SLA Nasil Calisir?

1. Bir talep (bug) durum degistirdiginde, eklenti aktif akistaki eslesen adimi bulur.
2. Adimin `sla_hours` degeri > 0 ise SLA takibi baslar.
3. SLA suresi **is saatleri** uzerinden hesaplanir (hafta sonu ve mesai disi saatler haric).
4. Onceki adimdaki SLA takibi otomatik olarak tamamlanir.

### SLA Durumlari

| Durum | Kosul | Gosterim |
|-------|-------|----------|
| **NORMAL** | SLA suresinin <%80'i | Yesil |
| **WARNING** | SLA suresinin %80'i doldu | Sari |
| **EXCEEDED** | SLA suresi doldu | Kirmizi |

### SLA Kontrol Sayfasi

Bu sayfa aktif SLA takiplerini tablo halinde gosterir:

| Sutun | Aciklama |
|-------|----------|
| Talep No | Tiklanabilir baglanti |
| Adim | Surecin hangi adiminda |
| Departman | Sorumlu departman |
| SLA (h) | Tanimlanan SLA suresi (saat) |
| Deadline | Son teslim tarih/saat |
| SLA Durumu | NORMAL / WARNING / EXCEEDED |
| Escalation | Eskalasyon seviyesi (Lv0, Lv1, Lv2) |

**"SLA Kontrol"** butonuna basarak manuel SLA kontrolu tetikleyebilirsiniz. Bu islem tum aktif SLA takiplerini degerlendirir ve gerekli bildirimleri gonderir.

---

## 7. Eskalasyon Sistemi

SLA asiminda otomatik eskalasyon devreye girer. 4 seviyeli bildirim sistemi:

### Eskalasyon Seviyeleri

| Seviye | Kosul | Alici | E-posta Konusu |
|--------|-------|-------|----------------|
| **UYARI (Sari)** | SLA suresinin %80'i doldu | Atanan kullanici | [MantisBT] SLA Uyarisi - Talep #X |
| **ASIM (Kirmizi)** | SLA suresi doldu | Atanan + departman yoneticisi | [MantisBT] SLA Asimi - Talep #X |
| **Eskalasyon Lv1** | SLA suresinin 1.5 kati | MANAGER rolu | [MantisBT] Eskalasyon Seviye 1 - Talep #X |
| **Eskalasyon Lv2** | SLA suresinin 2 kati | ADMINISTRATOR | [MantisBT] Eskalasyon Seviye 2 - Talep #X |

### Darbogazlar Sayfasi

**Erisim:** SLA Kontrol > **Darbogazlari Goster**

Bu sayfa en cok SLA asimi yasanan adimlari siralar:

| Sutun | Aciklama |
|-------|----------|
| # | Siralama |
| Adim | Adim adi |
| Departman | Departman adi |
| SLA Asim Sayisi | Toplam asim adedi |

Bu rapor, surec iyilestirme calismalari icin hangi adimlarin darbogaz olusturdugunu belirlemenize yardimci olur.

---

## 8. Surec Izleme ve Zaman Cizelgesi

### Otomatik Loglama

Bir talep durum degistirdiginde eklenti otomatik olarak:

1. Durum degisikligini `log_table`'a kaydeder
2. Aktif akistaki eslesen adimi bulur
3. SLA takibini baslatir/tamamlar
4. `EVENT_PROCESSENGINE_STATUS_CHANGED` ozel olayini tetikler

### Talep Detay Sayfasindaki Zaman Cizelgesi

Her talebin detay sayfasinda **"Surec Zaman Cizelgesi"** bolumleri otomatik olarak gosterilir:

| Sutun | Aciklama |
|-------|----------|
| Tarih | Degisiklik tarihi ve saati |
| Onceki Durum | Degisiklik oncesi MantisBT durumu |
| Yeni Durum | Degisiklik sonrasi MantisBT durumu |
| Kullanici | Islemi yapan kullanici |
| Adim | Surecte eslesen adim adi |
| Not | Varsa eklenen not |

---

## 9. Yapilandirma Sayfasi

**Erisim:** Yonetim > **Surec Motoru Ayarlari**
**Gerekli Yetki:** MANAGER

### Ayarlar

| Ayar | Tip | Aralik | Aciklama |
|------|-----|--------|----------|
| **Yonetim Erisim Seviyesi** | Secim | MantisBT erisim seviyeleri | Akis tasarimi ve yapilandirma icin gerekli minimum yetki |
| **Goruntuleme Erisim Seviyesi** | Secim | MantisBT erisim seviyeleri | Surec paneli ve zaman cizelgesini gorme yetkisi |
| **SLA Uyari Yuzdesi** | Sayi | 50-99 | SLA uyari e-postasinin gonderildigi esik degeri |
| **Is Saati Baslangic** | Sayi | 0-23 | SLA hesaplamasinda gunun baslangic saati |
| **Is Saati Bitis** | Sayi | 0-23 | SLA hesaplamasinda gunun bitis saati |
| **Calisma Gunleri** | Metin | 1-7 arasi | Virguelle ayrilmis gun numaralari (1=Pzt, 7=Paz) |

### Hizli Erisim Butonlari

Yapilandirma sayfasinin alt bolumunde:

- **Akis Tasarimcisi** - Dogrudan tasarimciya git
- **SLA Kontrol** - SLA izleme sayfasina git
- **Ornek Veri Yukle** - Hazir akis sablonlari yukle

---

## 10. Cron Gorevi

SLA kontrolunun otomatik calisabilmesi icin cron gorevi ayarlanmalidir.

### Manuel Calistirma

```bash
docker exec mantisbt php /var/www/html/scripts/sla_cron.php
```

### Crontab Ayari (Uretim Ortami)

Her 15 dakikada bir SLA kontrolu:

```cron
*/15 * * * * php /var/www/html/scripts/sla_cron.php > /dev/null 2>&1
```

### Cron Ne Yapar?

1. Tum aktif (completed_at IS NULL) SLA takiplerini tarar
2. Gecen is saatlerini hesaplar
3. Uyari esigini gecenlere WARNING durumu atar
4. SLA suresi dolanlara EXCEEDED durumu atar
5. 1.5x asimda Eskalasyon Lv1, 2x asimda Lv2 tetikler
6. Gerekli e-posta bildirimlerini gonderir

---

## 11. Departmanlar ve Roller

### Tanimli Departmanlar

| Departman | Tipik Gorevler |
|-----------|---------------|
| **Satis** | Fiyat talebi girisi, musteri iletisimi |
| **Fiyatlandirma** | Maliyet analizi, fiyat belirleme |
| **Satis Operasyon** | Siparis isleme, lojistik koordinasyonu |
| **Satinalma** | Tedarikci secimi, malzeme temini |
| **ArGe** | Teknik degerlendirme, urun gelistirme |
| **Yonetim** | Onay ve eskalasyon kararlari |
| **Kalite** | Kalite kontrol ve dogrulama |

### MantisBT Rolleri

Akis adimlarinda su roller atanabilir:

| Rol | MantisBT Erisim Seviyesi |
|-----|--------------------------|
| Reporter | 25 |
| Updater | 40 |
| Developer | 55 |
| Manager | 70 |
| Administrator | 90 |

---

## 12. Veritabani Yapisi

Tum tablolar `mantis_plugin_ProcessEngine_` on ekiyle olusturulur.

### flow_definition_table

| Alan | Tip | Aciklama |
|------|-----|----------|
| id | INT PK | Otomatik artan kimlik |
| name | VARCHAR(128) | Akis adi |
| description | LONGTEXT | Aciklama |
| status | SMALLINT | 0=Taslak, 1=Onay Bekliyor, 2=Aktif |
| project_id | INT | MantisBT proje ID (0=global) |
| created_by | INT | Olusturan kullanici ID |
| created_at | INT | Olusturma zamani (Unix timestamp) |
| updated_at | INT | Son guncelleme zamani |

### step_table

| Alan | Tip | Aciklama |
|------|-----|----------|
| id | INT PK | Otomatik artan kimlik |
| flow_id | INT FK | Ait oldugu akis |
| name | VARCHAR(128) | Adim adi |
| department | VARCHAR(64) | Departman |
| mantis_status | SMALLINT | Eslenen MantisBT durumu |
| sla_hours | INT | SLA suresi (saat) |
| step_order | INT | Siralama |
| role | VARCHAR(64) | Gerekli rol |
| position_x | INT | Kanvas X konumu |
| position_y | INT | Kanvas Y konumu |

### transition_table

| Alan | Tip | Aciklama |
|------|-----|----------|
| id | INT PK | Otomatik artan kimlik |
| flow_id | INT FK | Ait oldugu akis |
| from_step_id | INT FK | Kaynak adim |
| to_step_id | INT FK | Hedef adim |
| condition_field | VARCHAR(128) | Kosul alani (opsiyonel) |
| condition_value | VARCHAR(255) | Kosul degeri (opsiyonel) |

### log_table

| Alan | Tip | Aciklama |
|------|-----|----------|
| id | INT PK | Otomatik artan kimlik |
| bug_id | INT | MantisBT talep ID |
| flow_id | INT FK | Akis ID |
| step_id | INT FK | Adim ID |
| from_status | SMALLINT | Onceki MantisBT durumu |
| to_status | SMALLINT | Yeni MantisBT durumu |
| user_id | INT | Islemi yapan kullanici |
| note | LONGTEXT | Not |
| created_at | INT | Islem zamani |

### sla_tracking_table

| Alan | Tip | Aciklama |
|------|-----|----------|
| id | INT PK | Otomatik artan kimlik |
| bug_id | INT | MantisBT talep ID |
| step_id | INT FK | Adim ID |
| flow_id | INT FK | Akis ID |
| sla_hours | INT | Tanimlanan SLA suresi |
| started_at | INT | Baslangic zamani |
| deadline_at | INT | Son teslim zamani |
| completed_at | INT | Tamamlanma zamani (NULL=devam ediyor) |
| sla_status | VARCHAR(16) | NORMAL / WARNING / EXCEEDED |
| notified_warning | SMALLINT | Uyari bildirimi gonderildi mi (0/1) |
| notified_exceeded | SMALLINT | Asim bildirimi gonderildi mi (0/1) |
| escalation_level | SMALLINT | Eskalasyon seviyesi (0, 1, 2) |

---

## 13. Sorun Giderme

### Sik Karsilasilan Hatalar

#### "Table does not exist" hatasi
MantisBT veritabani tablolari olusturulmamis. `admin/install.php` sayfasindan veritabani kurulumunu tamamlayin.

#### "BLOB/TEXT column can't have a default value"
MySQL 8.0 strict mode'da LONGTEXT alanlarina DEFAULT deger atanamaz. ProcessEngine 1.0.0'da bu sorun duzeltilmistir.

#### "require_once failed to open stream"
Dosya yolu hatasi. Eklentinin `core/` klasorundeki dosyalarin mevcut oldugundan emin olun.

#### "APPLICATION ERROR #2800"
CSRF token dogrulama hatasi. Sayfalara dogrudan URL ile degil, form butonlari uzerinden erismeye dikkat edin.

#### SLA bildirimleri gelmiyor
1. Cron gorevinin ayarli oldugunu dogrulayin
2. MantisBT e-posta ayarlarini kontrol edin
3. MailHog (http://localhost:8025) uzerinden test e-postalarini kontrol edin

### Faydali Komutlar

```bash
# Eklenti tablolarini kontrol et
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis \
  -e "SHOW TABLES LIKE 'mantis_plugin_ProcessEngine_%';"

# Akis tanimlarini listele
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis \
  -e "SELECT id, name, status FROM mantis_plugin_ProcessEngine_flow_definition_table;"

# Aktif SLA takiplerini gor
docker exec mantis_mysql mysql -u mantis -pmantis123 mantis \
  -e "SELECT * FROM mantis_plugin_ProcessEngine_sla_tracking_table WHERE completed_at IS NULL;"

# Manuel SLA kontrolu calistir
docker exec mantisbt php /var/www/html/scripts/sla_cron.php

# MantisBT loglarini izle
docker compose logs -f mantisbt
```

---

## Hizli Baslangic Adimlari

1. Eklentiyi kurun ve etkinlestirin
2. **Yapilandirma** sayfasindan is saatleri ve SLA esigini ayarlayin
3. **Akis Tasarimcisi**'nda yeni bir akis olusturun
4. Adimlari ekleyin (departman, SLA suresi, MantisBT durumu atayarak)
5. Adimlar arasina gecisler cizin
6. **Dogrula** butonuyla akisi kontrol edin
7. **Yayinla** butonuyla akisi aktif hale getirin
8. MantisBT'de talep durum degisikliklerini izleyin
9. Cron gorevini ayarlayarak SLA kontrolunu otomatize edin
