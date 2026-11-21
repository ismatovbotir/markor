# CSV import

Bu sahifada yuklangan fayllar ro'yxati ko'rsatiladi. Har bir yuklash bitta partiya bo'ladi va fayl nomi bilan ataladi.

{{img:import-1}}

## Ro'yxat ustunlari

1. Partiya: fayl nomi.
2. Mahsulotlar: GTIN bo'yicha topilgan mahsulot nomi, uning ostida GTIN. Milliy klassifikatorda bunday mahsulot bo'lmasa, "Klassifikatorda topilmadi" yoziladi.
3. Kodlar soni.
4. To'lov summasi.
5. Holat.
6. Sana.

## Holatlar

Holat avtomatik o'zgaradi:

1. **Navbatda**
2. **Bazaga yuklanmoqda**
3. **Mahsulot tekshirilmoqda**
4. **Tayyor**

Muammo bo'lsa, holat **Xatolik** bo'ladi. Ro'yxat o'zi yangilanadi, sahifani qayta yuklash shart emas.

## O'tkazib yuborilgan qatorlar

1. Ba'zi qatorlar yuklanmasa, qizil havola chiqadi: "N ta o'tkazib yuborildi".
2. Sababini ko'rish uchun havolani bosing.
3. Sabablar: noto'g'ri qator yoki kod tizimda allaqachon bor. Har bir kodni faqat bir marta yuklash mumkin.

## To'lov

Yuqoridagi panelda shu oydagi kodlar soni × narx (har bir yuklangan kod uchun 9 so'm), chegirma va to'lov summasi ko'rsatiladi.

> Hozircha chegirma 100%, ya'ni xizmat bepul.

## Yorliq yaratish

1. Partiya holati **Tayyor** bo'lishini kuting.
2. **PDF yaratish** tugmasini bosing.


## Klassifikatorda topilmagan mahsulot

Ba'zan GTIN milliy klassifikatorda (tasnif.soliq.uz) bo'lmaydi. Unda partiya qatorida ⚠ belgisi va "Klassifikatorda topilmadi" yozuvi chiqadi.

1. **Nomini kiriting** havolasini bosing.
2. Kamida mahsulot nomini yozing (o'zbekcha yoki ruscha) va saqlang.
3. Partiyada bir nechta shunday mahsulot bo'lsa, keyingisi avtomatik ochiladi.

> PDF ni nom kiritmasdan ham yaratish mumkin, lekin etiketkada nom bo'lmaydi. Telegram ulangan bo'lsa, bot ham shu haqda xabar beradi.
