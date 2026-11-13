<?php
// This file is part of the SDU NLP plagiarism plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings, Kazakh.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'API кілті';
$string['apikey_help'] = 'API сервері іске қосылған API_KEY мәні. Серверде кілт орнатылмаған болса, бос қалдырыңыз.';
$string['apiurl'] = 'API мекенжайы';
$string['apiurl_help'] = 'Жобаның API серверінің мекенжайы, мысалы https://plagiarism.example.edu.kz. Сервер осы машинада немесе сенімді ішкі желіде болмаса, HTTPS қолданыңыз.';
$string['batchsize'] = 'Тапсырманың бір іске қосылуында тексерілетін жұмыстар саны';
$string['batchsize_help'] = 'Кесте бойынша тапсырма әр бес минут сайын іске қосылып, кезектегі элементтердің осы саннан аспайтын бөлігін тексереді.';
$string['checking'] = 'Тексеру';
$string['cmenable'] = 'Жұмыстарды ұқсастыққа тексеру';
$string['cmenable_help'] = '«Иә» таңдалса, тапсырылған әрбір файл мен мәтін осы тапсырманың басқа жұмыстарымен салыстырылады. Салыстыруды контекстік эмбеддинг моделі орындайды, сондықтан басқа сөздермен қайта жазылған сөйлемдер де табылады. Нәтижелер жұмыс тапсырылғаннан кейін бірнеше минуттан соң бағалау бетінде әр жұмыстың жанында көрсетіледі.';
$string['connection'] = 'API серверіне қосылу';
$string['defaulton'] = 'Жаңа тапсырмаларда қосулы';
$string['defaulton_help'] = 'Оқытушы тапсырма құрған кездегі «Жұмыстарды ұқсастыққа тексеру» параметрінің бастапқы мәні. Оқытушы оны өзгерте алады.';
$string['disclosure'] = 'Студенттерге көрсетілетін ескерту';
$string['disclosure_help'] = 'Тексеру қосылған әрбір тапсырманың жұмыс тапсыру бетінде көрсетіледі. Стандартты мәтінді қолдану үшін бос қалдырыңыз.';
$string['disclosuredefault'] = 'Осы тапсырмаға тапсырылған жұмыстар оқу орнының ұқсастықты тексеру қызметі арқылы осы тапсырманың басқа жұмыстарымен салыстырылады. Қызмет жұмыстың мәтінін сіздің атыңызсыз алады. Нәтиже — оқытушының сәйкес келген үзінділерді оқып шығуына негіз ғана, ол тәртіп бұзу туралы шешім емес.';
$string['enabled'] = 'Плагинді қосу';
$string['enabled_help'] = 'Плагиатты тексеру плагиндері сайт деңгейінде де қосулы болуы керек: Сайтты басқару, Қосымша мүмкіндіктер.';
$string['errorpdftotext'] = 'Бұл жол бойынша іске қосуға болатын бағдарлама табылмады';
$string['errorthreshold'] = '0-ден 1-ге дейінгі сан енгізіңіз немесе бос қалдырыңыз';
$string['errorurl'] = 'Мекенжай https:// немесе http:// деп басталуы керек';
$string['itemfile'] = 'Тапсырылған файл';
$string['itemonlinetext'] = 'Сайтта енгізілген мәтін';
$string['linkprefix'] = 'Ұқсастық тексеруі:';
$string['maxsources'] = 'Салыстырылатын басқа жұмыстардың ең көп саны';
$string['maxsources_help'] = 'Әрбір жұмыс осы тапсырмадағы басқа студенттердің немесе топтардың соңғы жұмыстарымен салыстырылады, олардың саны осы мәннен аспайды.';
$string['pdftotext'] = 'pdftotext бағдарламасының жолы';
$string['pdftotext_help'] = 'Poppler жинағындағы pdftotext бағдарламасының толық жолы, мысалы /usr/bin/pdftotext. PDF файлдары осы жол көрсетілгенде ғана тексеріледі. Мәтін қабаты жоқ сканерленген PDF файлдарын оқу мүмкін емес. Сайтта бағдарлама жолдарын веб-интерфейс арқылы орнатуға тыйым салынған болса, оны config.php файлында көрсетіңіз: $CFG->forced_plugin_settings[\'plagiarism_sduembed\'][\'pdftotext\'] = \'/usr/bin/pdftotext\';';
$string['plagiarismoff'] = 'Бұл сайтта плагиатты тексеру плагиндері өшірулі. Оларды қосыңыз: Сайтты басқару, Қосымша мүмкіндіктер, «Плагиат плагиндерін қосу».';
$string['pluginname'] = 'SDU NLP ұқсастық тексеруі';
$string['privacy:metadata:api'] = 'Жұмыстардың мәтіні сайт әкімшісі баптаған ұқсастық API серверіне жіберіледі. Эталондық сервер оны сақтамайды.';
$string['privacy:metadata:api:submissionid'] = 'Әрбір жұмыстың сандық идентификаторы. Аты-жөндер мен пайдаланушы идентификаторлары жіберілмейді.';
$string['privacy:metadata:api:submissiontext'] = 'Жұмыстың және онымен салыстырылатын басқа жұмыстардың мәтіні.';
$string['privacy:metadata:sub'] = 'Тапсырылған әрбір файл немесе мәтін бойынша ұқсастық тексеруінің нәтижесі.';
$string['privacy:metadata:sub:report'] = 'Сәйкес келген сөйлемдер жұптары.';
$string['privacy:metadata:sub:score'] = 'Басқа жұмыста жақын сәйкестігі бар сөйлемдердің үлесі.';
$string['privacy:metadata:sub:submitter'] = 'Жұмысты жүктеген пайдаланушы.';
$string['privacy:metadata:sub:timechecked'] = 'Тексеру уақыты.';
$string['privacy:metadata:sub:userid'] = 'Жұмыс иесі болып табылатын пайдаланушы.';
$string['privacy:path'] = 'Ұқсастық тексеруі';
$string['queuesummary'] = 'Кезек: күтуде — {$a->queued}, тексерілді — {$a->done}, сәтсіз — {$a->failed}. Ағымдағы баптаулармен оқылатын файл түрлері: {$a->types}.';
$string['recheckhours'] = 'Қайта тексеру аралығы, сағат';
$string['recheckhours_help'] = 'Жұмысты тек тексеру сәтінде тапсырылып қойған жұмыстармен салыстыруға болады. Кейін басқа студенттер жұмыс тапсырғанда, ертерек тапсырылған жұмыс соңғы тексеруден бері осынша сағат өткен соң қайта тексеріледі. Әр жұмысты бір-ақ рет тексеру үшін 0 енгізіңіз.';
$string['reportchecked'] = 'Тексерілген уақыты';
$string['reportcompared'] = 'Салыстырылған басқа жұмыстар';
$string['reportcosine'] = 'Косинус';
$string['reportexplain'] = 'Сөйлем мағынасы жағынан басқа жұмыстағы сөйлемге жақын болса белгіленеді; жақындық олардың контекстік эмбеддингтерінің арасындағы косинуспен өлшенеді. «Сәйкес келген мәтін» және «Жаккар» — сөзбе-сөз өлшемдер: осы жұмыстың басқа жұмыста да кездесетін үш сөзден тұратын тізбектерге кіретін үлесі және осындай тізбектердің екі жиынының қиылысуы.';
$string['reportitem'] = 'Тексерілген элемент';
$string['reportmodel'] = 'Модель';
$string['reportnomatches'] = 'Ешбір сөйлем шекке жеткен жоқ, сөзбе-сөз сәйкестік табылмады.';
$string['reportnotready'] = 'Бұл элемент бойынша есеп әзірге жоқ.';
$string['reportscore'] = 'Жақын сәйкестігі бар сөйлемдер';
$string['reportscorevalue'] = '{$a->percent}% ({$a->sentences} сөйлемнің {$a->flagged}-і)';
$string['reportsource'] = 'Басқа жұмыс {$a}';
$string['reportsourcestats'] = 'Белгіленген сөйлемдер: {$a->flagged} (осы жұмыстың {$a->share}%-ы). Сәйкес келген мәтін {$a->containment}%, Жаккар {$a->jaccard}, ең жақсы косинустың орташа мәні {$a->cosine}.';
$string['reportsrcsentence'] = 'Басқа жұмыстағы ең жақын сөйлем';
$string['reportsubsentence'] = 'Осы жұмыстағы сөйлем';
$string['reportthreshold'] = 'Шек (косинус)';
$string['reporttitle'] = 'Ұқсастық есебі';
$string['reviewnotice'] = 'Бұл есеп — адамның қарап шығуына негіз. Дұрыс рәсімделген дәйексөздер, жиі қолданылатын тіркестер және тапсырманың өз мәтіні де белгіленуі мүмкін. Қорытынды жасамас бұрын үзінділерді оқып, студентпен сөйлесіңіз.';
$string['scorelink'] = 'сөйлемдердің {$a->percent}%-ында жақын сәйкестік бар';
$string['scorelinktitle'] = 'Сәйкес келген сөйлемдер көрсетілген есепті ашу';
$string['sduembed'] = 'SDU NLP ұқсастық тексеруі';
$string['sduembed:enable'] = 'Курс элементінде ұқсастық тексеруін қосу және өшіру';
$string['sduembed:viewreport'] = 'Ұқсастық нәтижелері мен есептерін көру';
$string['statusfailed'] = 'тексеру сәтсіз аяқталды; сайт әкімшісіне хабарласыңыз';
$string['statusnosources'] = 'салыстыратын басқа жұмыстар әзірге жоқ';
$string['statusnotqueued'] = 'тексерілмеген (жұмыс тексеру қосылғанға дейін тапсырылған)';
$string['statusqueued'] = 'тексеруді күтуде';
$string['statustooshort'] = 'мәтін тексеру үшін тым қысқа';
$string['statusunsupported'] = 'бұл файл түрін оқу мүмкін емес';
$string['students'] = 'Студенттер';
$string['studentview'] = 'Студенттер өз нәтижесін көре алады';
$string['studentview_help'] = '«Иә» таңдалса, студент өз жұмысындағы белгіленген сөйлемдердің үлесін және сол сөйлемдердің өзін көреді. Басқа студенттердің жұмысындағы мәтін студенттерге ешқашан көрсетілмейді.';
$string['taskprocessqueue'] = 'Кезектегі жұмыстарды ұқсастық API-іне жіберу';
$string['testconnection'] = 'Сақтау және қосылуды тексеру';
$string['testfailed'] = 'API серверіне қосылу мүмкін болмады ({$a}). Мекенжайды және Moodle серверіне оған қосылуға рұқсат берілгенін тексеріңіз.';
$string['testkeybad'] = 'API сервері кілтті қабылдамады ({$a}).';
$string['testkeyok'] = 'API кілті қабылданды.';
$string['testkeyunknown'] = 'Сервердің бұл нұсқасы кілтті алдын ала тексере алмайды. Ол алғашқы тапсырылған жұмыспен бірге тексеріледі.';
$string['testok'] = 'API сервері жауап берді. Сервер нұсқасы {$a->version}, модель {$a->model}, шек {$a->threshold}.';
$string['threshold'] = 'Ұқсастық шегі';
$string['threshold_help'] = 'Екі сөйлем сәйкестік деп саналатын косинустық ұқсастықтың 0-ден 1-ге дейінгі ең төменгі мәні. API сервері калибрленген мәнді қолдану үшін бос қалдырыңыз (ұсынылады).';
$string['timeout'] = 'Бір сұраныстың уақыт шегі, секунд';
