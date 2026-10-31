<?php
// This file is part of Moodle - http://moodle.org/
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
 * Hebrew strings for mod_streamvoice.
 *
 * @package    mod_streamvoice
 * @copyright  2026 CentricApp LTD, Dev Team (dev@centricapp.co)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Stream Voice';
$string['modulename'] = 'Stream Voice';
$string['modulenameplural'] = 'Stream Voice';
$string['modulename_help'] = 'הסטודנט מגיש קובץ. Stream Voice נועל אותו ומריץ הגנה בעל פה: זמן הכנה, תשובות מוקלטות, תמלול, ציון לפי מחוון, ובדיקת עקביות מול העבודה שהוגשה. המטרה לוודא שהסטודנט מבין את העבודה ומסוגל לנמק אותה, לא לזהות אם כלי כתב את הקובץ.';
$string['pluginadministration'] = 'ניהול Stream Voice';
$string['privacy:metadata'] = 'Stream Voice שומר הגשות, הקלטות, תמלולים וציונים ב-Stream. Moodle שומר את הגדרות הפעילות ואת הציון בגליון הציונים.';

$string['streamvoice:view'] = 'צפייה ב-Stream Voice';
$string['streamvoice:submit'] = 'הגשת עבודה ב-Stream Voice';
$string['streamvoice:addinstance'] = 'הוספת Stream Voice';
$string['streamvoice:grade'] = 'צפייה בדוחות Stream Voice';

$string['deflang'] = 'שפת Stream Voice';
$string['lang_he'] = 'עברית';
$string['lang_en'] = 'English';
$string['defensehdr'] = 'Stream Voice';
$string['prepseconds'] = 'זמן הכנה (שניות)';
$string['prepseconds_help'] = 'כמה זמן יש לסטודנט לקרוא את השאלות ולרשום הערות לפני שההקלטה נפתחת. Stream אוכף את השעון.';
$string['answerseconds'] = 'זמן תשובה לכל שאלה (שניות)';
$string['answerseconds_help'] = 'האורך המרבי של כל תשובה מוקלטת. Stream דוחה הקלטה ארוכה יותר כשאפשר למדוד את הקובץ.';
$string['questioncount'] = 'מספר שאלות';
$string['questioncount_help'] = 'שאלות קבועות של המרצה נשאלות קודם. את השאר Stream בונה מתוך הקובץ שהוגש.';
$string['fixedquestions'] = 'שאלות קבועות';
$string['fixedquestions_help'] = 'שאלה בכל שורה. הן נשאלות כלשונן. השאירו ריק כדי ש-Stream יבנה את כל השאלות מההגשה.';
$string['rubric'] = 'מחוון';
$string['rubric_help'] = 'נשלח ל-Stream ומשמש לציון ההסבר בעל פה. אם ריק, Stream משתמש במחוון ברירת מחדל: הבנה, נימוק, עקביות מול הקובץ, וחשיבה עצמאית.';
$string['weightfile'] = 'משקל העקביות מול הקובץ';
$string['weightfile_help'] = 'האחוז בציון שמגיע מההתאמה בין ההסבר לקובץ הנעול. יחד עם משקל ההסבר בעל פה הסכום חייב להיות 100.';
$string['weightoral'] = 'משקל ההסבר בעל פה';
$string['weightsmust100'] = 'שני המשקלים חייבים להסתכם ב-100.';
$string['allowresubmission'] = 'לאפשר קובץ חדש לפני תחילת ההקלטה';
$string['allowresubmission_help'] = 'אחרי ההקלטה הראשונה Stream משאיר את הקובץ נעול.';
$string['maxbytes'] = 'גודל קובץ מרבי';
$string['questioncountrange'] = 'יש לבחור בין שאלה אחת ל-8 שאלות.';
$string['preprange'] = 'זמן ההכנה חייב להיות בין 0 ל-3600 שניות.';
$string['answerrange'] = 'זמן התשובה חייב להיות בין 30 ל-900 שניות.';

$string['artifact'] = 'קובץ העבודה';
$string['artifact_help'] = 'קוד, מסמך, או zip של הפרויקט. אחרי ההגשה Stream Voice נועל את הקובץ.';
$string['artifactnote'] = 'הערה לבודק (לא חובה)';
$string['submitandlock'] = 'הגשה ונעילה';
$string['beforelock'] = 'אחרי ההגשה הקובץ ננעל ומתחיל Stream Voice. תתבקשו להסביר את העבודה מול המצלמה.';
$string['lockednotice'] = 'הקובץ נעול. המשיכו ל-Stream Voice למטה.';
$string['continuedefense'] = 'המשך ל-Stream Voice';
$string['status_awaiting_model'] = 'ממתין לציון אוטומטי';
$string['status_awaiting_transcript'] = 'ממתין לתמלול';
$string['status_provisional'] = 'ציון זמני';
$string['status_prep'] = 'בזמן הכנה';
$string['status_recording'] = 'בהקלטה';
$string['backtosubmissions'] = 'חזרה להגשות';
$string['report'] = 'דוח Stream Voice';
$string['none'] = 'עדיין אין פעילות Stream Voice בקורס הזה.';

$string['streamnotconfigured'] = 'Stream לא מוגדר. הגדירו כתובת ומפתח API תחת ניהול האתר ← תוספים ← תוספים מקומיים ← Stream.';
$string['error_unreachable'] = 'Stream לא ענה. בדקו את כתובת Stream ושה-API של Stream Voice זמין.';
$string['error_invalid_key'] = 'Stream דחה את מפתח ה-API.';
$string['error_locked'] = 'ההגשה הזו כבר נעולה.';
$string['error_not_open'] = 'עדיין אי אפשר להגיש.';
$string['error_closed'] = 'ההגשה נסגרה.';
$string['error_missing_file'] = 'בחרו קובץ להגשה.';
$string['error_file_too_large'] = 'הקובץ גדול מהגודל המותר.';
$string['error_unknown_activity'] = 'הפעילות עדיין לא רשומה ב-Stream. פתחו אותה שוב אחרי השמירה.';
$string['error_no_submission'] = 'יש להגיש ולנעול את העבודה לפני תחילת Stream Voice.';
$string['error_not_configured'] = 'Stream לא מוגדר.';
