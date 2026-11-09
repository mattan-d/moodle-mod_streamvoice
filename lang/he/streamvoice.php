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
$string['questionsettings'] = 'שאלות מההגשה';
$string['questionsettings_desc'] = 'שאלות שהמרצה כתב בפעילות נשאלות תמיד. שני המספרים כאן חלים על השאלות ש-Stream יוצר מתוך הקובץ, ועל ההגשה הבאה. הגנה שכבר התחילה לא משתנה.';
$string['generatecount'] = 'מספר שאלות על בסיס ההגשה';
$string['generatecount_help'] = 'כמה שאלות Stream יוצר מתוך הקובץ שהוגש. בין 1 ל-8.';
$string['answercount'] = 'מספר שאלות למענה';
$string['answercount_help'] = 'כמה מתוך השאלות שנוצרו הסטודנט בוחר ועונה. לא יותר ממספר השאלות שנוצרות. אם המספרים שווים, אין בחירה.';
$string['answercountrange'] = 'מספר השאלות למענה חייב להיות בין 1 למספר השאלות שנוצרות מההגשה.';
$string['questionlimits'] = 'שאלות מההגשה';
$string['questionlimits_desc'] = 'Stream יוצר {$a->generate} שאלות מתוך הקובץ. הסטודנט עונה על {$a->answer} מתוכן. זה נקבע בהגדרות התוסף.';
$string['questionskipped'] = 'הסטודנט לא בחר בשאלה הזו.';
$string['fixedquestions'] = 'שאלות קבועות';
$string['fixedquestions_help'] = 'שאלה בכל שורה. הן נשאלות תמיד, בנוסף לשאלות ש-Stream יוצר מההגשה. השאירו ריק אם אין שאלות חובה.';
$string['rubric'] = 'מחוון';
$string['rubric_help'] = 'נשלח ל-Stream ומשמש לציון ההסבר בעל פה. אם ריק, Stream משתמש במחוון ברירת מחדל: הבנה, נימוק, עקביות מול הקובץ, וחשיבה עצמאית.';
$string['weightfile'] = 'משקל ציון העבודה';
$string['weightfile_help'] = 'האחוז של ציון העבודה בציון הסופי. יחד עם משקל ציון ההגנה הסכום חייב להיות 100.';
$string['weightoral'] = 'משקל ציון ההגנה';
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
$string['answers'] = 'תשובות';
$string['answerscore'] = 'ציון';
$string['gradeoutofrange'] = 'הציון חייב להיות בין 0 ל-{$a}.';
$string['transcript'] = 'תמלול';
$string['consistency'] = 'עקביות מול הקובץ';
$string['oralscore'] = 'הסבר בעל פה';
$string['gradebreakdown'] = '{$a->grade} = עקביות {$a->consistency} × {$a->fileweight}% + הסבר בעל פה {$a->oral} × {$a->oralweight}%';
$string['recommendedgrade'] = 'ציון מומלץ';
$string['aidefense'] = 'ציון בינה להגנה';
$string['workgrade'] = 'ציון לעבודה';
$string['defensegrade'] = 'ציון להגנה';
$string['answernum'] = 'תשובה {$a}';
$string['defensehow'] = 'כל תשובה מקבלת ציון מ־0 עד 100 לפי המחוון. ציון ההגנה הוא הממוצע של התשובות, ומוצג מתוך {$a}.';
$string['defensesame'] = 'ההערה זהה בכל התשובות. הציון של כל תשובה נפרד, לפי מה שנאמר בה.';
$string['oralrubricused'] = 'המחוון שלפיו נקבע הציון';
$string['oralrubricedit'] = 'מחוון להגנות הבאות';
$string['oralrubricnote'] = 'שינוי כאן חל על הגנות שייבדקו מכאן והלאה. ציונים שכבר מוצגים לא מחושבים מחדש.';
$string['workrubric'] = 'מחוון לעבודה';
$string['workrubricintro'] = 'מחוון סמינריון של המוסד. בחירת רמה בכל קריטריון קובעת את ציון העבודה. עריכה משנה את המחוון של הפעילות הזו, לא את המחוון המשותף.';
$string['editworkrubric'] = 'עריכת המחוון';
$string['seminarubric'] = 'עבודת סמינריון';
$string['seminarubric_desc'] = 'מחוון מוסדי לעבודת סמינריון. אפשר לעדכן קריטריונים ורמות בכל פעילות.';
$string['research'] = 'שאלת מחקר ומיקוד';
$string['research0'] = 'אין שאלת מחקר, או שהיא לא קשורה לעבודה.';
$string['research1'] = 'יש נושא, אבל השאלה רחבה או לא ממוקדת.';
$string['research2'] = 'שאלה ברורה, עם היקף סביר.';
$string['research3'] = 'שאלה ממוקדת, שמנחה את כל העבודה.';
$string['sources'] = 'ביסוס ומקורות';
$string['sources0'] = 'טענות בלי מקור, או מקורות לא רלוונטיים.';
$string['sources1'] = 'מעט מקורות, והשימוש בהם חלקי.';
$string['sources2'] = 'מקורות רלוונטיים, מצוטטים במקום.';
$string['sources3'] = 'מקורות מגוונים שמשמשים לטיעון, לא רק לרשימה.';
$string['argument'] = 'טיעון וניתוח';
$string['argument0'] = 'תיאור בלי ניתוח.';
$string['argument1'] = 'ניתוח חלקי, עם קפיצות בטיעון.';
$string['argument2'] = 'טיעון עקבי שנשען על המקורות.';
$string['argument3'] = 'ניתוח שמבחין בין טענות, ראיות ומגבלות.';
$string['structure'] = 'מבנה, כתיבה ומסקנות';
$string['structure0'] = 'קשה לעקוב, ובלי מסקנה.';
$string['structure1'] = 'מבנה חלקי, והמסקנה חוזרת על הגוף.';
$string['structure2'] = 'מבנה ברור, ומסקנה שעונה על השאלה.';
$string['structure3'] = 'מבנה שמשרת את הטיעון, ומסקנה שמזהה גם מה נשאר פתוח.';
$string['aidiscretion'] = 'ציון הבינה גלוי רק למרצה. אפשר לאמץ אותו כציון ההגנה, או לשנות אותו לפי שיקול הדעת.';
$string['gradeparts'] = '{$a->final} = עבודה {$a->work} × {$a->fileweight}% + הגנה {$a->defense} × {$a->oralweight}%';
$string['bothgradesrequired'] = 'יש להזין ציון לעבודה וציון להגנה.';
$string['recommendednote'] = 'הסבר לציון המומלץ';
$string['previewunavailable'] = 'אי אפשר להציג את הקובץ הזה כאן. אפשר להוריד אותו ולפתוח אותו.';
$string['noanswers'] = 'עדיין אין תשובות מוקלטות.';
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

$string['recordtest'] = 'בדיקת הקלטה';
$string['recordtest_desc'] = 'מקליטים כמה שניות בדפדפן ושומעים אותן מיד. הבדיקה לא נשמרת ולא נשלחת ל-Stream. היא משתמשת באותה פתיחה של מצלמה ומיקרופון כמו בהגנה.';
$string['recordteststart'] = 'פתיחת בדיקת ההקלטה';
$string['recordtestintro'] = 'אומרים כמה מילים, עוצרים, ומאזינים. התמונה בזמן ההקלטה נשארת בלי קול כדי שלא יהיה הד. ההשמעה למטה היא ההקלטה עצמה, עם קול. שום דבר לא מועלה.';
$string['recordtestrecord'] = 'הקלטה';
$string['recordteststop'] = 'עצירה';
$string['recordtestlevel'] = 'עוצמת המיקרופון';
$string['recordtestmic'] = 'מיקרופון';
$string['recordtestsound'] = 'המיקרופון נשמע בהקלטה הזו.';
$string['recordtestsilent'] = 'מההתקן «{$a}» לא הגיע קול. אם זה לא המיקרופון שלכם, בחרו אחר למעלה ונסו שוב.';
$string['recordtestfailed'] = 'לא ניתן לפתוח את המצלמה או את המיקרופון.';
