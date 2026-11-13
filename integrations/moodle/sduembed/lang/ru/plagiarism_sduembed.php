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
 * Language strings, Russian.
 *
 * @package    plagiarism_sduembed
 * @copyright  2026 SDU NLP, SDU University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['apikey'] = 'Ключ API';
$string['apikey_help'] = 'Значение API_KEY, с которым запущен сервер API. Оставьте пустым, если на сервере ключ не задан.';
$string['apiurl'] = 'Адрес API';
$string['apiurl_help'] = 'Адрес сервера API проекта, например https://plagiarism.example.edu.kz. Используйте HTTPS, если сервер не находится на той же машине или в доверенной внутренней сети.';
$string['batchsize'] = 'Работ за один запуск задачи';
$string['batchsize_help'] = 'Задача по расписанию запускается каждые пять минут и проверяет не более этого числа элементов из очереди.';
$string['checking'] = 'Проверка';
$string['cmenable'] = 'Проверять работы на сходство';
$string['cmenable_help'] = 'Если выбрано «Да», каждый сданный файл и текст сравнивается с другими работами по этому заданию. Сравнение выполняет модель контекстных эмбеддингов, поэтому находятся и предложения, пересказанные другими словами. Результаты появляются рядом с каждой работой на странице оценивания через несколько минут после сдачи.';
$string['connection'] = 'Подключение к серверу API';
$string['defaulton'] = 'Включена в новых заданиях';
$string['defaulton_help'] = 'Начальное значение параметра «Проверять работы на сходство» при создании задания. Преподаватель может его изменить.';
$string['disclosure'] = 'Уведомление для студентов';
$string['disclosure_help'] = 'Показывается на странице сдачи работы в каждом задании, где включена проверка. Оставьте пустым, чтобы использовать стандартный текст.';
$string['disclosuredefault'] = 'Работы, сданные в этом задании, сравниваются с другими работами по этому же заданию с помощью службы проверки сходства вашего учебного заведения. Служба получает текст работы без вашего имени. Результат — это повод для преподавателя прочитать совпавшие фрагменты, а не решение о нарушении.';
$string['enabled'] = 'Включить плагин';
$string['enabled_help'] = 'Плагины проверки на плагиат также должны быть включены для сайта: Администрирование, Расширенные возможности.';
$string['errorpdftotext'] = 'По этому пути не найдена программа, которую можно запустить';
$string['errorthreshold'] = 'Введите число от 0 до 1 или оставьте поле пустым';
$string['errorurl'] = 'Адрес должен начинаться с https:// или http://';
$string['itemfile'] = 'Сданный файл';
$string['itemonlinetext'] = 'Текст, введённый на сайте';
$string['linkprefix'] = 'Проверка сходства:';
$string['maxsources'] = 'Наибольшее число других работ для сравнения';
$string['maxsources_help'] = 'Каждая работа сравнивается с последними работами не более чем этого числа других студентов или групп по тому же заданию.';
$string['pdftotext'] = 'Путь к pdftotext';
$string['pdftotext_help'] = 'Полный путь к программе pdftotext из пакета Poppler, например /usr/bin/pdftotext. Файлы PDF проверяются, только если путь задан. Сканированные PDF без текстового слоя прочитать нельзя. Если на сайте запрещено задавать пути к программам через веб-интерфейс, укажите путь в config.php: $CFG->forced_plugin_settings[\'plagiarism_sduembed\'][\'pdftotext\'] = \'/usr/bin/pdftotext\';';
$string['plagiarismoff'] = 'Плагины проверки на плагиат выключены для этого сайта. Включите их: Администрирование, Расширенные возможности, «Включить плагины плагиата».';
$string['pluginname'] = 'Проверка сходства SDU NLP';
$string['privacy:metadata:api'] = 'Текст работ отправляется на сервер API проверки сходства, заданный администратором сайта. Эталонный сервер его не сохраняет.';
$string['privacy:metadata:api:submissionid'] = 'Числовой идентификатор каждой работы. Имена и идентификаторы пользователей не отправляются.';
$string['privacy:metadata:api:submissiontext'] = 'Текст работы и других работ, с которыми она сравнивается.';
$string['privacy:metadata:sub'] = 'Результат проверки сходства для каждого сданного файла или текста.';
$string['privacy:metadata:sub:report'] = 'Пары совпавших предложений.';
$string['privacy:metadata:sub:score'] = 'Доля предложений с близким совпадением в другой работе.';
$string['privacy:metadata:sub:submitter'] = 'Пользователь, загрузивший работу.';
$string['privacy:metadata:sub:timechecked'] = 'Время проверки.';
$string['privacy:metadata:sub:userid'] = 'Пользователь, которому принадлежит работа.';
$string['privacy:path'] = 'Проверка сходства';
$string['queuesummary'] = 'Очередь: ожидают — {$a->queued}, проверено — {$a->done}, не удалось — {$a->failed}. Типы файлов, которые читаются при текущих настройках: {$a->types}.';
$string['recheckhours'] = 'Повторная проверка через, часов';
$string['recheckhours_help'] = 'Работу можно сравнить только с теми работами, которые уже сданы на момент проверки. Когда позже сдают другие студенты, более ранняя работа проверяется снова, если с её последней проверки прошло указанное число часов. Укажите 0, чтобы проверять каждую работу только один раз.';
$string['reportchecked'] = 'Проверено';
$string['reportcompared'] = 'Сравнено с другими работами';
$string['reportcosine'] = 'Косинус';
$string['reportexplain'] = 'Предложение отмечается, если оно близко по смыслу к предложению из другой работы; близость измеряется косинусом между их контекстными эмбеддингами. «Совпавший текст» и «Жаккар» — дословные меры: доля этой работы, входящая в последовательности из трёх слов, которые есть и в другой работе, и пересечение двух множеств таких последовательностей.';
$string['reportitem'] = 'Проверенный элемент';
$string['reportmodel'] = 'Модель';
$string['reportnomatches'] = 'Ни одно предложение не достигло порога, дословных совпадений не найдено.';
$string['reportnotready'] = 'Отчёта для этого элемента пока нет.';
$string['reportscore'] = 'Предложения с близким совпадением';
$string['reportscorevalue'] = '{$a->percent}% ({$a->flagged} из {$a->sentences} предложений)';
$string['reportsource'] = 'Другая работа {$a}';
$string['reportsourcestats'] = 'Отмечено предложений: {$a->flagged} ({$a->share}% этой работы). Совпавший текст {$a->containment}%, Жаккар {$a->jaccard}, средний лучший косинус {$a->cosine}.';
$string['reportsrcsentence'] = 'Самое близкое предложение в другой работе';
$string['reportsubsentence'] = 'Предложение в этой работе';
$string['reportthreshold'] = 'Порог (косинус)';
$string['reporttitle'] = 'Отчёт о сходстве';
$string['reviewnotice'] = 'Этот отчёт — повод для проверки человеком. Отмеченными могут оказаться правильно оформленные цитаты, общеупотребительные выражения и формулировки задания. Прочитайте фрагменты и поговорите со студентом, прежде чем делать вывод.';
$string['scorelink'] = 'у {$a->percent}% предложений есть близкое совпадение';
$string['scorelinktitle'] = 'Открыть отчёт с совпавшими предложениями';
$string['sduembed'] = 'Проверка сходства SDU NLP';
$string['sduembed:enable'] = 'Включать и выключать проверку сходства в элементе курса';
$string['sduembed:viewreport'] = 'Просматривать результаты и отчёты о сходстве';
$string['statusfailed'] = 'проверка не удалась; обратитесь к администратору сайта';
$string['statusnosources'] = 'пока нет других работ для сравнения';
$string['statusnotqueued'] = 'не проверено (работа сдана до включения проверки)';
$string['statusqueued'] = 'ожидает проверки';
$string['statustooshort'] = 'текст слишком короткий для проверки';
$string['statusunsupported'] = 'файл этого типа прочитать нельзя';
$string['students'] = 'Студенты';
$string['studentview'] = 'Студенты видят свой результат';
$string['studentview_help'] = 'Если выбрано «Да», студент видит долю отмеченных предложений в своей работе и сами отмеченные предложения. Текст чужих работ студентам не показывается.';
$string['taskprocessqueue'] = 'Отправка работ из очереди в API проверки сходства';
$string['testconnection'] = 'Сохранить и проверить подключение';
$string['testfailed'] = 'Не удалось связаться с сервером API ({$a}). Проверьте адрес и то, что серверу Moodle разрешено к нему подключаться.';
$string['testkeybad'] = 'Сервер API отклонил ключ ({$a}).';
$string['testkeyok'] = 'Ключ API принят.';
$string['testkeyunknown'] = 'Эта версия сервера не позволяет проверить ключ заранее. Он будет проверен при первой сданной работе.';
$string['testok'] = 'Сервер API ответил. Версия сервера {$a->version}, модель {$a->model}, порог {$a->threshold}.';
$string['threshold'] = 'Порог сходства';
$string['threshold_help'] = 'Косинусное сходство от 0 до 1, начиная с которого два предложения считаются совпадением. Оставьте пустым, чтобы использовать значение, с которым откалиброван сервер API (рекомендуется).';
$string['timeout'] = 'Предельное время одного запроса, секунды';
