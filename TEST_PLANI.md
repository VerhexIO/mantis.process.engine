# Test Planı — Tek Sorun, Çoklu Adım Geliştirmeleri

## Ön Koşullar

1. Docker ortamı çalışıyor olmalı (`docker compose up -d`)
2. MantisBT'ye admin ile giriş yapılmış olmalı (http://localhost:8080)
3. Plugin "Yönet > Eklentileri Yönet" sayfasından **yükseltilmiş** olmalı (schema #9 için)
4. En az bir proje oluşturulmuş olmalı
5. Projede en az 2-3 kullanıcı tanımlı olmalı

---

## Faz 1: Otomatik Sorumlu Atama

### TEST-1.1: Schema Doğrulama
**Amaç:** `handler_id` sütununun step tablosuna eklendiğini doğrula

**Adımlar:**
1. Terminal'de çalıştır:
   ```bash
   docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e "DESCRIBE mantis_plugin_ProcessEngine_step_table;"
   ```
2. Çıktıda `handler_id` sütununu ara

**Beklenen Sonuç:**
- `handler_id` sütunu listelenmeli
- Tipi `int(11) unsigned`, varsayılan değeri `0` olmalı

---

### TEST-1.2: Akış Tasarımcısında Sorumlu Kişi Dropdown
**Amaç:** Adım düzenleme modalında "Sorumlu Kişi" seçeneğinin göründüğünü doğrula

**Adımlar:**
1. Yönet > Akış Tasarımcısı'na git
2. Mevcut bir akışı aç (veya yeni oluştur)
3. Bir adıma çift tıkla (düzenleme modalını aç)
4. "Sorumlu Kişi" dropdown'ını kontrol et

**Beklenen Sonuç:**
- Modal'da "Sorumlu Kişi" alanı görünmeli
- İlk seçenek "Otomatik atama yok" olmalı
- Proje kullanıcıları listede yer almalı

---

### TEST-1.3: Sorumlu Kişi Kaydetme
**Amaç:** Seçilen handler_id'nin veritabanına kaydedildiğini doğrula

**Adımlar:**
1. Akış tasarımcısında bir adım modalını aç
2. "Sorumlu Kişi" dropdown'ından bir kullanıcı seç
3. Kaydet butonuna tıkla
4. "Kaydet" (üst toolbar) butonuna tıkla
5. Sayfayı yenile
6. Aynı adımın modalını tekrar aç

**Beklenen Sonuç:**
- Seçilen kullanıcı hâlâ dropdown'da seçili olmalı
- DB doğrulama:
  ```bash
  docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e "SELECT id, name, handler_id FROM mantis_plugin_ProcessEngine_step_table;"
  ```

---

### TEST-1.4: Yeni Sorun — Başlangıç Adımı Handler Atama
**Amaç:** Yeni sorun oluşturulduğunda başlangıç adımının handler'ının atandığını doğrula

**Ön Koşul:** Aktif bir akış olmalı ve başlangıç adımına bir sorumlu kişi atanmış olmalı

**Adımlar:**
1. Akış tasarımcısında başlangıç adımına bir kullanıcı (örn: "testuser") ata
2. Akışı kaydet ve yayınla
3. İlgili projede yeni bir sorun oluştur
4. Sorunun detay sayfasını aç

**Beklenen Sonuç:**
- Sorunun "Atanan" (handler) alanı "testuser" olmalı

---

### TEST-1.5: Durum Değişikliği — Sonraki Adım Handler Atama
**Amaç:** Durum değiştiğinde yeni adımın handler'ının otomatik atandığını doğrula

**Ön Koşul:** Aktif akışta 2. adıma farklı bir sorumlu kişi (örn: "user2") atanmış olmalı

**Adımlar:**
1. TEST-1.4'te oluşturulan sorunu aç
2. Durumu bir sonraki adımın MantisBT durumuna değiştir (örn: "yeni" → "geri bildirim")
3. Sorunu güncelle

**Beklenen Sonuç:**
- Sorunun "Atanan" alanı "user2" olarak değişmeli
- Sorun notlarında "Otomatik atama: user2 sorumlu olarak atandı." notu görünmeli
- DB doğrulama:
  ```bash
  docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e "SELECT id, handler_id FROM mantis_bug_table ORDER BY id DESC LIMIT 1;"
  ```

---

### TEST-1.6: Handler Atanmamış Adım — Atama Yapılmaz
**Amaç:** handler_id=0 olan adımlarda otomatik atama yapılmadığını doğrula

**Adımlar:**
1. Akış tasarımcısında bir adımın sorumlu kişisini "Otomatik atama yok" olarak bırak
2. Akışı kaydet/yayınla
3. Bir sorunu o adımın durumuna geçir

**Beklenen Sonuç:**
- Sorunun mevcut handler'ı değişmemeli
- Otomatik atama notu eklenmemeli

---

## Faz 2: Görsel Adım İlerleme Çubuğu

### TEST-2.1: Süreç Bilgi Paneli
**Amaç:** Sorun detayında süreç bilgi panelinin doğru göründüğünü doğrula

**Adımlar:**
1. Süreç takibinde olan bir sorunun detay sayfasını aç
2. "Süreç Bilgisi" widget'ını kontrol et

**Beklenen Sonuç:**
- "Süreç Bilgisi" başlıklı bir panel görünmeli
- Panel şu bilgileri içermeli:
  - **Mevcut Adım:** Aktif adımın adı
  - **Departman:** Adımın departmanı
  - **İlerleme:** "Adım X / Y" formatında
  - **SLA Kalan:** Kalan süre (saat) veya "Süre aşıldı"
  - **Sorumlu:** Atanan kişinin adı veya "-"

---

### TEST-2.2: Görsel Stepper — Adım Durumları
**Amaç:** Stepper'daki dairelerin doğru renklerde gösterildiğini doğrula

**Adımlar:**
1. 4-5 adımlı bir akış oluştur ve yayınla
2. Yeni sorun oluştur (1. adımda)
3. Sorun detayını aç — stepper'ı kontrol et
4. Durumu değiştirerek 2. adıma geç
5. Sorun detayını tekrar aç

**Beklenen Sonuç:**
- **Adım 3'te:** 1. ve 2. adım yeşil (completed), 3. adım mavi (current), 4-5. adım gri (pending)
- Her dairenin altında adım adı ve departman yazmalı
- Tamamlanan adımlar arası bağlantı çizgisi yeşil olmalı

---

### TEST-2.3: Stepper — İlk Adımda
**Amaç:** İlk adımdayken stepper'ın doğru göründüğünü doğrula

**Adımlar:**
1. Yeni sorun oluştur
2. Detay sayfasını aç

**Beklenen Sonuç:**
- 1. adım mavi (current), geri kalan adımlar gri (pending)
- Bağlantı çizgileri gri

---

### TEST-2.4: Zaman Çizelgesi — Mevcut İşlevsellik Korundu
**Amaç:** Zaman çizelgesi tablosunun hâlâ doğru çalıştığını doğrula

**Adımlar:**
1. Birkaç durum değişikliği yapılmış bir sorunun detay sayfasını aç
2. "Süreç Zaman Çizelgesi" bölümünü kontrol et

**Beklenen Sonuç:**
- Tarih, Önceki Durum, Yeni Durum, Kullanıcı, Adım, Not sütunları görünmeli
- Her durum değişikliği bir satır olarak listelenmeli
- `from_status=0` olan satırlarda "-" gösterilmeli

---

### TEST-2.5: Süreç Verisi Olmayan Sorun
**Amaç:** Süreç takibinde olmayan sorunlarda widget'ların gösterilmediğini doğrula

**Adımlar:**
1. Aktif akışı olmayan bir projede sorun oluştur
2. Detay sayfasını aç

**Beklenen Sonuç:**
- Süreç Bilgisi, Stepper ve Zaman Çizelgesi bölümleri görünmemeli

---

## Faz 3: Dashboard Geliştirmeleri

### TEST-3.1: Departman Filtresi
**Amaç:** Departman dropdown filtresinin çalıştığını doğrula

**Adımlar:**
1. Süreç Paneli (Dashboard) sayfasını aç
2. Departman dropdown'ını kontrol et — "Tüm Departmanlar" varsayılan seçili olmalı
3. "Satış" departmanını seç

**Beklenen Sonuç:**
- Sayfa yenilenmeli, URL'de `&department=Sat%C4%B1%C5%9F` parametresi olmalı
- Tabloda sadece "Satış" departmanındaki sorunlar gösterilmeli
- "Tüm Departmanlar" seçildiğinde tüm sorunlar gösterilmeli

---

### TEST-3.2: Departman + Durum Filtresi Birlikte
**Amaç:** Departman ve durum filtrelerinin birlikte çalıştığını doğrula

**Adımlar:**
1. Dashboard'da "Aktif" filtre butonuna tıkla
2. Departman dropdown'ından "Fiyatlandırma" seç

**Beklenen Sonuç:**
- URL'de `&filter=active&department=Fiyatland%C4%B1rma` parametreleri olmalı
- Sadece aktif ve Fiyatlandırma departmanındaki sorunlar gösterilmeli

---

### TEST-3.3: İlerleme Sütunu
**Amaç:** İlerleme çubuğunun doğru yüzdeyle gösterildiğini doğrula

**Adımlar:**
1. Dashboard'u aç
2. "İlerleme" sütununu kontrol et

**Beklenen Sonuç:**
- Her satırda bir ilerleme çubuğu (progress bar) görünmeli
- Yüzde değeri mantıklı olmalı (örn: 5 adımlı akışta 2. adımdaysa ~40%)
- %100'e ulaşan çubuklar yeşil, diğerleri mavi olmalı

---

### TEST-3.4: Sorumlu Sütunu
**Amaç:** Sorumlu sütununun doğru kişiyi gösterdiğini doğrula

**Adımlar:**
1. Dashboard'u aç
2. "Sorumlu" sütununu kontrol et

**Beklenen Sonuç:**
- Handler atanmış sorunlarda kullanıcı adı görünmeli
- Handler atanmamış sorunlarda "-" görünmeli

---

### TEST-3.5: Tablo Başlıkları
**Amaç:** Yeni sütun başlıklarının doğru gösterildiğini doğrula

**Adımlar:**
1. Dashboard'u aç
2. Tablo başlıklarını kontrol et

**Beklenen Sonuç:**
Sütun sırası: Talep No | Başlık | Mevcut Adım | Departman | **İlerleme** | **Sorumlu** | SLA Durumu | Güncelleme

---

## Faz 4: Dil Dosyaları

### TEST-4.1: Türkçe String'ler
**Amaç:** Tüm yeni Türkçe string'lerin doğru yüklendiğini doğrula

**Adımlar:**
1. MantisBT dili Türkçe olarak ayarla
2. Aşağıdaki sayfalara git ve metinleri kontrol et:
   - Akış Tasarımcısı → adım modalı → "Sorumlu Kişi", "Otomatik atama yok"
   - Sorun detayı → "Süreç Bilgisi", "Mevcut Adım", "İlerleme", "SLA Kalan", "Sorumlu"
   - Dashboard → "Tüm Departmanlar", "İlerleme", "Sorumlu" sütunları

**Beklenen Sonuç:**
- Tüm metinler Türkçe görünmeli, `???` veya hata mesajı olmamalı

---

### TEST-4.2: İngilizce String'ler
**Amaç:** İngilizce string'lerin de tanımlı olduğunu doğrula

**Adımlar:**
1. MantisBT dilini English olarak değiştir
2. Aynı sayfaları kontrol et

**Beklenen Sonuç:**
- "Responsible Person", "No auto assignment", "Process Info", "Progress" vb. İngilizce metinler görünmeli

---

## Regresyon Testleri

### TEST-R1: Mevcut Akış Kaydetme
**Amaç:** handler_id olmayan eski akışların sorunsuz kaydedilebildiğini doğrula

**Adımlar:**
1. Eski (handler_id atanmamış) bir akışı aç
2. Bir adımın adını değiştir
3. Kaydet

**Beklenen Sonuç:**
- Hata olmadan kaydedilmeli
- handler_id varsayılan olarak 0 kalmalı

---

### TEST-R2: Akış Doğrulama ve Yayınlama
**Amaç:** Doğrulama ve yayınlama işlemlerinin hâlâ çalıştığını doğrula

**Adımlar:**
1. Yeni akış oluştur, 3 adım ve 2 geçiş ekle
2. Doğrula butonuna tıkla
3. Yayınla butonuna tıkla

**Beklenen Sonuç:**
- Doğrulama başarılı mesajı
- Yayınlama başarılı, durum AKTİF olmalı

---

### TEST-R3: SLA Takibi
**Amaç:** SLA takibinin hâlâ doğru çalıştığını doğrula

**Adımlar:**
1. SLA süresi tanımlı adımları olan bir akış yayınla
2. Sorun oluştur, durum değiştir
3. SLA tablosunu kontrol et:
   ```bash
   docker exec mantis_mysql mysql -u mantis -pmantis123 mantis -e "SELECT * FROM mantis_plugin_ProcessEngine_sla_tracking_table ORDER BY id DESC LIMIT 5;"
   ```

**Beklenen Sonuç:**
- Yeni SLA kaydı oluşturulmuş olmalı
- Eski adımın SLA'sı completed olmalı

---

## Hızlı Kontrol Listesi

| # | Test | Durum |
|---|------|-------|
| 1.1 | Schema handler_id sütunu | [ ] |
| 1.2 | Modal'da sorumlu kişi dropdown | [ ] |
| 1.3 | Sorumlu kişi kaydetme | [ ] |
| 1.4 | Yeni sorun — başlangıç handler | [ ] |
| 1.5 | Durum değişikliği — handler atama | [ ] |
| 1.6 | Handler=0 — atama yapılmaz | [ ] |
| 2.1 | Süreç bilgi paneli | [ ] |
| 2.2 | Stepper renkleri | [ ] |
| 2.3 | Stepper ilk adım | [ ] |
| 2.4 | Zaman çizelgesi korundu | [ ] |
| 2.5 | Süreç verisi olmayan sorun | [ ] |
| 3.1 | Departman filtresi | [ ] |
| 3.2 | Departman + durum filtresi | [ ] |
| 3.3 | İlerleme sütunu | [ ] |
| 3.4 | Sorumlu sütunu | [ ] |
| 3.5 | Tablo başlıkları | [ ] |
| 4.1 | Türkçe string'ler | [ ] |
| 4.2 | İngilizce string'ler | [ ] |
| R1 | Eski akış kaydetme | [ ] |
| R2 | Doğrulama/yayınlama | [ ] |
| R3 | SLA takibi | [ ] |
