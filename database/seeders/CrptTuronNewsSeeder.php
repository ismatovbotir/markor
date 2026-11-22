<?php

namespace Database\Seeders;

use App\Domain\Content\NewsPost;
use Illuminate\Database\Seeder;

/**
 * Recent news of CRPT Turon (operator of Asl Belgisi), retold briefly in our own words with a link to the original.
 * Safe to run again: a post is matched by source_url and never overwritten (admin edits stay).
 */
class CrptTuronNewsSeeder extends Seeder
{
    private const SOURCE = 'CRPT Turon';

    public function run(): void
    {
        $added = 0;
        foreach (self::POSTS as $p) {
            if (NewsPost::where('source_url', $p['url'])->exists()) {
                continue;
            }
            NewsPost::create([
                'slug' => NewsPost::freeSlug($p['uz'][0]),
                'title_uz' => $p['uz'][0], 'excerpt_uz' => $p['uz'][1], 'body_uz' => $p['uz'][2],
                'title_ru' => $p['ru'][0], 'excerpt_ru' => $p['ru'][1], 'body_ru' => $p['ru'][2],
                'title_zh' => $p['zh'][0], 'excerpt_zh' => $p['zh'][1], 'body_zh' => $p['zh'][2],
                'source_name' => self::SOURCE, 'source_url' => $p['url'],
                'published_at' => $p['date'] . ' 09:00:00', 'is_active' => true,
            ]);
            $added++;
        }
        $this->command?->info("CRPT Turon news added: $added");
    }

    /** [title, excerpt, body] per language. */
    private const POSTS = [
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/asl-belgisi-connect-marketing-aksiyasini-o-tkazish-bo-yicha-ommaviy-oferta/10508/',
            'date' => '2026-09-16',
            'uz' => ['“Asl Belgisi Connect” marketing aksiyasi bo‘yicha ommaviy oferta',
                'Asl Belgisi Telegram va Instagram sahifalariga obuna bo‘lganlar orasida sovg‘alar o‘ynaldi.',
                "CRPT Turon 2026-yil 22-sentabrdan 6-oktabrgacha O‘zbekiston bo‘ylab ijtimoiy tarmoqlarda aksiya o‘tkazdi.\n\nIshtirok uchun Asl Belgisi Telegram va Instagram sahifalariga obuna bo‘lish, aksiya postiga layk bosish va uch do‘stni belgilash kerak edi. Tasodifiy tanlangan o‘n ishtirokchiga aqlli kolonkalar, simsiz quloqchinlar va karta g‘iloflari va’da qilindi."],
            'ru' => ['Публичная оферта маркетинговой акции «Asl Belgisi Connect»',
                'Розыгрыш призов среди подписчиков Telegram и Instagram системы Asl Belgisi.',
                "CRPT Turon провёл акцию в соцсетях по всему Узбекистану с 22 сентября по 6 октября 2026 года.\n\nДля участия нужно было подписаться на Telegram и Instagram Asl Belgisi, поставить лайк посту акции и отметить трёх друзей. Десять случайно выбранных участников получают умные колонки, беспроводные наушники и картхолдеры."],
            'zh' => ['“Asl Belgisi Connect”营销活动公开要约',
                '在 Asl Belgisi 的 Telegram 和 Instagram 关注者中抽奖。',
                "CRPT Turon 于 2026 年 9 月 22 日至 10 月 6 日在乌兹别克斯坦全国开展社交媒体活动。\n\n参与者需关注 Asl Belgisi 的 Telegram 和 Instagram 账号、为活动帖点赞并标记三位好友。随机抽取的十名参与者可获得智能音箱、无线耳机和卡套。"],
        ],
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/raqamli-markirovka-jadal-rivojlanmoqda-crpt-turon-va-korzinka-o-zbekiston-chakana-savdosi-uchun-yangi-imkoniyatlar-ochmoqda/10487/',
            'date' => '2025-12-02',
            'uz' => ['CRPT Turon va Korzinka chakana savdoda markirovkani kuzatish bo‘yicha memorandum imzoladi',
                'Suv va alkogolsiz ichimliklar kodlari kassada skanerlanadi.',
                "CRPT Turon Korzinka do‘konlar tarmog‘i kompaniyasi bilan hamkorlik memorandumini imzoladi. Maqsad — markirovkalangan suv va alkogolsiz ichimliklarni chakana savdoda to‘liq kuzatish.\n\nTomonlar o‘quv tadbirlari, tajriba almashish va seminarlar o‘tkazishni rejalashtirgan. Korzinka kassalarda markirovka kodlarini skanerlab, sotuvdan chiqarishni qayd etadigan uskunalarni bosqichma-bosqich joriy qilmoqda."],
            'ru' => ['CRPT Turon и Korzinka подписали меморандум о прослеживании маркировки в рознице',
                'Коды воды и безалкогольных напитков будут сканироваться на кассе.',
                "CRPT Turon подписал меморандум о сотрудничестве с компанией сети магазинов Korzinka. Цель — полное прослеживание маркированной воды и безалкогольных напитков в рознице.\n\nСтороны планируют обучение, обмен опытом и семинары. Korzinka поэтапно внедряет на кассах оборудование, которое сканирует коды маркировки и фиксирует выбытие товара."],
            'zh' => ['CRPT Turon 与 Korzinka 签署零售标识追溯备忘录',
                '瓶装水和软饮料的标识码将在收银台扫描。',
                "CRPT Turon 与 Korzinka 连锁超市所属公司签署合作备忘录，目标是在零售环节全面追溯已标识的瓶装水和软饮料。\n\n双方计划开展培训、经验交流和研讨会。Korzinka 正在分阶段为收银台配备扫描标识码并记录商品售出的设备。"],
        ],
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/diqqat-asl-belgisi-tizimida-rejalashtirilgan-texnik-ishlar-2/10477/',
            'date' => '2025-11-28',
            'uz' => ['Asl Belgisi tizimida rejalashtirilgan texnik ishlar',
                '28-noyabrdan 1-dekabrgacha shaxsiy kabinet, API va mobil ilovalar ishlamadi.',
                "Asl Belgisi tizimida 2025-yil 28-noyabr soat 20:00 dan 1-dekabr soat 08:00 gacha texnik ishlar o‘tkazildi. Bu vaqtda shaxsiy kabinet, API va mobil ilovalar mavjud bo‘lmadi.\n\n1-dekabrdan API yangilanib, yangi xTrace manzili orqali ishlashi e’lon qilindi. Ishlar davomida yaratilgan hisobotlar keyin qayta ishlanadi; foydalanuvchilarga markirovka kodlarini oldindan zaxiralash tavsiya qilindi."],
            'ru' => ['Плановые технические работы в системе Asl Belgisi',
                'С 28 ноября по 1 декабря были недоступны личный кабинет, API и мобильные приложения.',
                "В системе Asl Belgisi с 20:00 28 ноября до 08:00 1 декабря 2025 года проводились технические работы. Личный кабинет, API и мобильные приложения в это время не работали.\n\nС 1 декабря API обновлён и доступен по новому адресу xTrace. Отчёты, созданные во время работ, обрабатываются после их завершения; пользователям советовали заранее запастись кодами маркировки."],
            'zh' => ['Asl Belgisi 系统计划内技术维护',
                '11 月 28 日至 12 月 1 日期间个人账户、API 和移动应用暂停服务。',
                "Asl Belgisi 系统于 2025 年 11 月 28 日 20:00 至 12 月 1 日 08:00 进行技术维护，期间个人账户、API 和移动应用无法使用。\n\n自 12 月 1 日起 API 完成更新，并通过新的 xTrace 地址提供服务。维护期间生成的报告在维护结束后处理；建议用户提前储备标识码。"],
        ],
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/o-zbekistonda-o-g-itlar-va-o-simliklarni-himoya-qilish-vositalarini-raqamli-markirovkalash-bo-yicha-tajriba-yakunlandi/10452/',
            'date' => '2025-11-27',
            'uz' => ['O‘g‘itlar va o‘simliklarni himoya qilish vositalarini markirovkalash tajribasi yakunlandi',
                '5,3 milliondan ortiq birlik markirovkalandi; majburiy markirovka 2026-yil 1-maydan bosqichma-bosqich.',
                "O‘zbekistonda mineral o‘g‘itlar va o‘simliklarni himoya qilish vositalarini raqamli markirovkalash bo‘yicha tajriba yakunlandi. Besh ishlab chiqaruvchi 5,3 milliondan ortiq birlikni markirovkaladi.\n\nIxtiyoriy ravishda 19 kompaniya qo‘shilib, 2,5 milliondan ortiq birlikni markirovkaladi. Natijalar 2026-yil 1-maydan bu mahsulotlar uchun bosqichma-bosqich majburiy markirovkaga o‘tishga asos bo‘ladi."],
            'ru' => ['Завершён пилот по маркировке удобрений и средств защиты растений',
                'Промаркировано более 5,3 млн единиц; обязательная маркировка — поэтапно с 1 мая 2026 года.',
                "В Узбекистане завершился пилотный проект по цифровой маркировке минеральных удобрений и средств защиты растений. Пять производителей промаркировали более 5,3 млн единиц.\n\nДобровольно присоединились 19 компаний, промаркировавших свыше 2,5 млн единиц. Итоги пилота станут основой для поэтапного перехода к обязательной маркировке этих товаров с 1 мая 2026 года."],
            'zh' => ['化肥和植保产品数字标识试点结束',
                '已标识超过 530 万件；自 2026 年 5 月 1 日起分阶段强制标识。',
                "乌兹别克斯坦矿物化肥和植物保护产品数字标识试点项目结束，五家生产商共标识超过 530 万件产品。\n\n另有 19 家企业自愿参与，标识超过 250 万件。试点结果将作为自 2026 年 5 月 1 日起对这些产品分阶段实施强制标识的依据。"],
        ],
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/ommaviy-oferta-shartnomasi/tovar-aylanmasi-ishtirokchilariga-marketing-aksiyasida-qatnashish-taklifi-yangicha-markirovka-qiling-katta-yutuqqa-erishing-2/10431/',
            'date' => '2025-11-18',
            'uz' => ['«Yangicha markirovka qiling – katta yutuqqa erishing» aksiyasi',
                'Yangi xTrace kabinetida ro‘yxatdan o‘tgan tovar aylanmasi ishtirokchilari uchun sovg‘alar.',
                "CRPT Turon 2025-yil 24-noyabrdan 28-dekabrgacha yangi xTrace shaxsiy kabinetida ro‘yxatdan o‘tgan tovar aylanmasi ishtirokchilari uchun aksiya e’lon qildi.\n\nAksiya to‘rt toifani qamradi: dori vositalari, pivo, suv va alkogolsiz ichimliklar, tamaki. Har bir toifada smartfon, televizor va maishiy texnika kabi uchtadan sovg‘a; g‘oliblar tasodifiy tanlanadi."],
            'ru' => ['Акция «Маркируй по-новому — выигрывай по-крупному»',
                'Призы для участников оборота, зарегистрировавшихся в новом кабинете xTrace.',
                "CRPT Turon объявил акцию для участников оборота товаров, зарегистрировавшихся в новом личном кабинете xTrace, с 24 ноября по 28 декабря 2025 года.\n\nАкция охватывала четыре товарные группы: лекарства, пиво, вода и безалкогольные напитки, табак. В каждой — по три приза, например смартфоны, телевизор и бытовая техника; победители выбираются случайным образом."],
            'zh' => ['“新方式标识，赢取大奖”活动',
                '面向在新版 xTrace 个人账户注册的商品流通参与者的奖励活动。',
                "CRPT Turon 宣布于 2025 年 11 月 24 日至 12 月 28 日，面向在新版 xTrace 个人账户注册的商品流通参与者开展活动。\n\n活动涵盖四类商品：药品、啤酒、瓶装水和软饮料、烟草。每类设三个奖品，如智能手机、电视和小家电；获奖者随机抽取。"],
        ],
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/raqamli-markirovka-va-chakana-savdo-toshkentda-sohaning-rivojlanish-muammolari-va-tendensiyalari-muhokama-qilindi/10411/',
            'date' => '2025-10-31',
            'uz' => ['Raqamli markirovka va chakana savdo: Toshkentda soha muammolari muhokama qilindi',
                'Yopiq biznes uchrashuvda markirovkalangan tovarlar savdosidagi qiyinchiliklar ko‘rib chiqildi.',
                "2025-yil 29-oktabrda Toshkentda markirovkalangan tovarlarning chakana savdosiga bag‘ishlangan yopiq biznes uchrashuv bo‘ldi. CRPT Turon loyiha bosqichlari va holati haqida ma’lumot berdi.\n\nKorzinka to‘rtta do‘konida suv va alkogolsiz ichimliklar kodlarini kassada skanerlash boshlanganini aytdi. Talablarga rioya qilish va jarimalar ham muhokama qilindi."],
            'ru' => ['Цифровая маркировка и ритейл: в Ташкенте обсудили проблемы отрасли',
                'На закрытой бизнес-встрече разобрали сложности торговли маркированными товарами.',
                "29 октября 2025 года в Ташкенте прошла закрытая бизнес-встреча о розничной торговле маркированными товарами. CRPT Turon рассказал об этапах и статусе проекта.\n\nKorzinka сообщила, что в четырёх её магазинах начали сканировать коды воды и безалкогольных напитков на кассе. Также обсуждались требования и штрафы."],
            'zh' => ['数字标识与零售：塔什干讨论行业挑战',
                '闭门商务会议探讨了标识商品零售中的难点。',
                "2025 年 10 月 29 日，塔什干举行了关于标识商品零售的闭门商务会议，CRPT Turon 介绍了项目各阶段及现状。\n\nKorzinka 表示其四家门店已开始在收银台扫描瓶装水和软饮料的标识码。会议还讨论了合规要求和处罚。"],
        ],
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/xalqaro-e-tirof-crpt-turon-korrupsiyaga-qarshi-kurashish-xartiyasiga-a-zo-bo-ldi/10400/',
            'date' => '2025-10-22',
            'uz' => ['CRPT Turon Korrupsiyaga qarshi kurashish xartiyasiga a’zo bo‘ldi',
                'A’zolik sertifikati Toshkentdagi xalqaro forumlarda topshirildi.',
                "2025-yil 21-oktabrda Toshkentda bo‘lib o‘tgan Xalqaro biznes-ombudsmanlar forumi va IV Biznes halolligi forumida CRPT Turon korrupsiyaga qarshi xartiyaga a’zolik sertifikatini oldi.\n\nForumlarda bir necha davlat ombudsmanlari ishtirok etdi. Kompaniya a’zolikni Asl Belgisi tizimining shaffoflikdagi rolining e’tirofi deb baholadi."],
            'ru' => ['CRPT Turon стал членом Антикоррупционной хартии',
                'Сертификат участника вручён на международных форумах в Ташкенте.',
                "21 октября 2025 года на Международном форуме бизнес-омбудсменов и IV Форуме деловой честности в Ташкенте CRPT Turon получил сертификат участника антикоррупционной хартии.\n\nВ форумах участвовали омбудсмены нескольких стран. Компания считает членство признанием роли системы Asl Belgisi в прозрачности бизнеса."],
            'zh' => ['CRPT Turon 加入反腐败宪章',
                '成员证书在塔什干国际论坛上颁发。',
                "2025 年 10 月 21 日，在塔什干举行的国际商业监察员论坛和第四届商业诚信论坛上，CRPT Turon 获得反腐败宪章成员证书。\n\n多国监察员出席论坛。公司认为此次入选是对 Asl Belgisi 系统在商业透明度方面作用的认可。"],
        ],
        [
            'url' => 'https://crpt-turon.uz/uz/yangiliklar/crpt-turon-va-o-zkimyosanoat-aj-o-rtasida-hamkorlik-memorandumi-imzolandi/10389/',
            'date' => '2025-10-21',
            'uz' => ['CRPT Turon va «O‘zkimyosanoat» AJ hamkorlik memorandumini imzoladi',
                'Mineral o‘g‘itlar va o‘simliklarni himoya qilish vositalarini raqamli markirovkalash bo‘yicha.',
                "CRPT Turon va «O‘zkimyosanoat» AJ mineral o‘g‘itlar va o‘simliklarni himoya qilish vositalarini raqamli markirovkalash bo‘yicha memorandum imzoladi. Hujjat 2025-yildagi hukumat qarorini amalga oshirishga qaratilgan.\n\nMemorandum qalbakilashtirishga qarshi kurash, bozor shaffofligi, qo‘shma o‘quv tadbirlari va markirovka loyihalarini muvofiqlashtirishni qamraydi."],
            'ru' => ['CRPT Turon и АО «Узкимёсаноат» подписали меморандум о сотрудничестве',
                'О цифровой маркировке минеральных удобрений и средств защиты растений.',
                "CRPT Turon и АО «Узкимёсаноат» подписали меморандум о цифровой маркировке минеральных удобрений и средств защиты растений. Документ направлен на исполнение постановления правительства 2025 года.\n\nМеморандум охватывает борьбу с контрафактом, прозрачность рынка, совместное обучение и согласование проектов маркировки."],
            'zh' => ['CRPT Turon 与 Uzkimyosanoat 股份公司签署合作备忘录',
                '涉及矿物化肥和植保产品的数字标识。',
                "CRPT Turon 与乌兹别克化工工业股份公司（Uzkimyosanoat）签署矿物化肥和植物保护产品数字标识备忘录，旨在落实 2025 年政府决议。\n\n备忘录涵盖打击假冒、市场透明、联合培训以及标识项目的协调。"],
        ],
    ];
}
