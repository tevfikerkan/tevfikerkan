# Mockup Templates

Bu klasöre gerçekçi mockup template'leri ekleyin. Sistem otomatik olarak tüm template'leri tarar ve mockup üretiminde kullanır.

## Template Kaynakları

### Ücretsiz:
- **Mr.Mockup**: https://mrmockup.com/free-poster-mockups/
- **Freepik**: https://www.freepik.com/free-photos-vectors/poster-mockup
- **Mockupnest**: https://mockupnest.com/free-wall-poster-mockup/
- **Resource Boy**: https://resourceboy.com/mockups/poster/
- **Pixeden**: https://www.pixeden.com/free-graphics
- **GraphicBurger**: https://graphicburger.com/mock-ups/

## Template Format

Her template klasörü için:
1. **template.jpg veya template.png** - Mockup görseli
2. **config.json** - Koordinat ve metadata

### config.json Yapısı:

```json
{
  "name": "Wall Poster A4",
  "size": "A4 (21x29.7 cm)",
  "file": "template.jpg",
  "insert_area": {
    "x": 245,
    "y": 180,
    "width": 510,
    "height": 720
  },
  "variant": "wall_a4",
  "is_primary": true
}
```

### Alan Açıklamaları:

- **name**: Kullanıcıya gösterilecek mockup adı
- **size**: Ürün boyutu (opsiyonel)
- **file**: Template dosya adı (JPG veya PNG)
- **insert_area**: AI portresinin yerleştirileceği alan
  - **x**: Sol üst köşe X koordinatı (piksel)
  - **y**: Sol üst köşe Y koordinatı (piksel)
  - **width**: Alan genişliği (piksel)
  - **height**: Alan yüksekliği (piksel)
- **variant**: Benzersiz template ID'si (klasör adı kullanılabilir)
- **is_primary**: Ana mockup olarak gösterilsin mi (true/false)

## Koordinatları Nasıl Bulunur

### Yöntem 1: Photoshop
1. PSD template'i aç
2. Rectangle Marquee Tool (M) seç
3. Boş poster alanını seç
4. Window > Info panelinden koordinatları oku
   - X, Y: Sol üst köşe
   - W, H: Genişlik ve yükseklik

### Yöntem 2: GIMP (Ücretsiz)
1. Template'i GIMP'te aç
2. Rectangle Select Tool seç
3. Boş poster alanını seç
4. Tool Options'dan koordinatları oku

### Yöntem 3: Online Araçlar
- https://www.mobilefish.com/services/record_mouse_coordinates/record_mouse_coordinates.php
- Template'i yükleyip fare ile koordinatları belirle

## Adım Adım Template Ekleme

1. **Mockup İndir**
   ```
   Mr.Mockup veya diğer kaynaklardan PSD/JPG indir
   ```

2. **Klasör Oluştur**
   ```
   mkdir wall-poster-a4
   cd wall-poster-a4
   ```

3. **Template Export Et**
   - PSD'yi JPG olarak export et (kalite: 90-100%)
   - Dosya adı: `template.jpg`

4. **Koordinatları Bul**
   - Photoshop/GIMP'te boş alan koordinatlarını belirle
   - Not al: x, y, width, height

5. **config.json Oluştur**
   ```json
   {
     "name": "Wall Poster A4",
     "size": "A4 (21x29.7 cm)",
     "file": "template.jpg",
     "insert_area": {
       "x": 245,
       "y": 180,
       "width": 510,
       "height": 720
     },
     "variant": "wall_a4",
     "is_primary": true
   }
   ```

6. **Test Et**
   - WordPress'te yeni bir AI portre oluştur
   - Mockup'ların üretildiğini kontrol et

## Klasör Yapısı

```
assets/mockup-templates/
├── README.md
├── wall-poster-a4/
│   ├── template.jpg
│   └── config.json
├── wall-poster-a3/
│   ├── template.jpg
│   └── config.json
├── lifestyle/
│   ├── template.jpg
│   └── config.json
└── mug-print/
    ├── template.jpg
    └── config.json
```

## Önerilen Template Tipleri

1. **Duvar Posterleri** (Wall Posters)
   - Çerçeveli duvar posterleri
   - Çerçevesiz posterler
   - Farklı boyutlar (A4, A3, A2)

2. **Yaşam Tarzı** (Lifestyle)
   - Ev dekorasyonunda posterler
   - Ofis ortamında posterler
   - Cafe/restaurant sahneleri

3. **Baskı Ürünleri** (Print Products)
   - Kupa baskıları
   - T-shirt baskıları
   - Yastık kılıfları
   - Telefon kılıfları

## Notlar

- Template dosyaları yüksek çözünürlüklü olmalı (min 1500px genişlik)
- insert_area oranları A4 kağıt (1:1.414) ile uyumlu olmalı
- Sistem otomatik olarak AI portresini ölçeklendirir ve yerleştirir
- Her klasör bir mockup tipini temsil eder
- Yeni template eklerken mevcut mockup cache'i temizlenir
