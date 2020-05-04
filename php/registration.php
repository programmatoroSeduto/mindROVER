<?php
session_start();

//funzioni di libreria
require_once('./db/mysql_credentials.php');
require_once('./db/CredenzialiUtenti.php');
require_once('./db/ProfiliUtenti.php');
require_once('./db//ImgProfilo.php');
require_once('./utils/hashMethods.php');
require_once('./utils/sanitize_input.php');

$hash = new HashMethods();

//controllo delle informazioni in ingresso
$email = "";
$firstname = "";
$lastname = "";
$pass = "";
{
    $confirm = "";

    //verifica delle informazioni provenienti dal client
    //se qualcosa manca, ritorna messaggio d'errore e chiudi lo script
    function verify_data($data)
    {
        if(isset($_POST[$data]))
        {
            return sanitize($_POST[$data]);
        }
        else
        {
            return null;
        }
    }
    if(!($email = verify_data("email")))
    {
        //echo "ERRORE: dato mancante. ->" . "email";
        header('location: ../html/registrazione.php&' . 'error_no_email=true');
        die();
    }

    //verifica che la mail sia effettivamente una mail
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) 
    {
        //echo "ERRORE: non è una mail. ->" . "email";
        header('location: ../html/registrazione.php&' . 'error_email=true');
        die();
    }
        
    if(!($firstname = verify_data("firstname")))
    {
        //echo "ERRORE: dato mancante. ->" . "firstname";
        header('location: ../html/registrazione.php&' . 'error_no_first=true');
        die();
    }
    if(!($lastname = verify_data("lastname")))
    {
        //echo "ERRORE: dato mancante. ->" . "lastname";
        header('location: ../html/registrazione.php&' . 'error_no_last=true');
        die();
    }
    if(!($pass = verify_data("pass")))
    {
        //echo "ERRORE: dato mancante. ->" . "pass";
        header('location: ../html/registrazione.php&' . 'error_no_pass=true');
        die();
    }
    if(!($confirm = verify_data("confirm")))
    {
        //echo "ERRORE: dato mancante. ->" . "confirm";
        header('location: ../html/registrazione.php&' . 'error_no_confirm=true');
        die();
    }

    //posso verificare subito che le due password coincidano
    if($pass !== $confirm)
    {
        /*
        echo "ERRORE nella conferma delle password!<br>" 
            . "password: $pass <br>"
            . "conferma: $confirm<br>";
        */
        header('location: ../html/registrazione.php&' . 'error_pass_confirm=true');
        die();
    }
}

//hashare la password prima di metterla nel db
$hashOfPass = $hash->getHash($pass);

//connessione col database
$dbms = connect();
$table_credenziali = new CredenzialiUtenti($dbms, $hash);
$table_profili = new ProfiliUtenti($dbms);

//verifica prima che la mail non esista già
if($table_credenziali->isSetEmail($email))
{
    //l'email esiste già; rifiuta la registrazione
    //echo 'la mail ' . $email . ' esiste già nel database. REGISTRAZIONE RIFIUTATA.';
    header('location: ../html/registrazione.php&' . 'error_email=true');
    die();
}

//prima di registrare l'utente, gli assegno automaticamente un nickname
/*
    per garantire l'unicità del nickname assegnato automaticamente dal sistema,
    1-  provo prima con nomecognome; se esiste ...
    2-  provo con nomecognome<codice di 4 cifre>; se esiste...
    3-  faccio le seguenti prove fin quando non trovo il nickname giusto:
        a-  aumento di 1 il codice generato (sempre di 4 cifre) per un tot di tentativi; ad ogni tentativo provo il nuovo nick generto; se ancora non va ...
        b-  genero random il codice e provo; se ancora non va bene, torno al passo a-
    
    continuo così fin quando non sono riuscito a trovare il nickname giusto; questo potrebbe portare a qualche problema...
    ... ma quante persone vuoi che ci siano di nome MarioRossi? ho 8999 codici! è possibile avere 8999 posti occupati? per un sito così piccolo, no...
*/
$nickname = '' . $firstname . $lastname;
if($table_profili->getIdByNickname($nickname) >= 0)
{
    $random_code = rand(1000, 9999);
    $nickname = '' . $firstname . $lastname . $random_code;

    $n_attemps = 5;
    $counter = $n_attemps;
    while($table_profili->getIdByNickname($nickname) >= 0)
    {
        if($counter > 0)
        {
            $random_code = ($random_code == 9999 ? 1000 : $random_code + 1);
            $counter--;
        }
        else
        {
            $random_code = rand(1000, 9999);
            $counter = $n_attemps;
        }
        $nickname = '' . $firstname . $lastname . $random_code;
    }
}

//registrazione del profilo nel database
if($table_credenziali->createAccount($email, $hashOfPass) === -1)
{
    echo "errore: " . $dbms->errno . ' ' . $dbms->error;
    die();
}

//ottieni l'id appena registrato
$id_profilo = $table_credenziali->getId($email, $pass);
if($id_profilo === -1)
{
    //die("errore nella ricerca dell'id!");
    header('location: ../html/registrazione.php&' . 'il_garbato_distruttore_colpisce_ancora=true');
    die();
}

//registrazione dei dati di profilo
if($errcode = $table_profili->createAccount($id_profilo, $nickname, $firstname, $lastname, (new ImgProfilo($dbms))->getFirstAvailableStyleId()))
{
    /*
    echo $errcode . "<br>" . $dbms->errno . " - " . $dbms->error . "<br>";
    die("errore!");
    */
    header('location: ../html/registrazione.php&' . 'il_garbato_distruttore_colpisce_ancora=true');
    die();
}

//registrazione completata con successo; ora, fai qualcosa
//echo 'registrazione completata.';
if(isset($_GET['target']))
{
    if($_GET['target'] === 'profilo')
        header('location: ../html/profiloprivato.php');
    elseif($_GET['target'] === 'crowdfunding')
        header('location: ../html/crowdfunding.php'); //modificare...
    /*
    elseif($_GET['target'] === '')
        header('location: ');
    */
    else
        header('location: ../html/profiloprivato.php');
}
else
{
    header('location: ../html/profiloprivato.php');
}

?>