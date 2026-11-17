<?php
chdir($_SERVER['DOCUMENT_ROOT']);

require_once("./Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

global $ilUser;

$lang = $_GET['lang'];
if ( in_array( CLIENT_ID, ['env'] ) && $ilUser instanceof ilObjUser ) {
    if ( in_array($lang, ['en','fr']) ) {
        $ilUser->setLanguage($lang);
        $ilUser->update();
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));


?>
