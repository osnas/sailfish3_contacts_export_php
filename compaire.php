<?php

/*
 * This script does: 
 *   -- query the contacts3.db from sailfishOS v3 and brings all contacts and their details
 *   -- query the contacts4.db from sailfishOS v4 and checks which contacts do not exist
 *   -- generates a vcf which contains in vcf2.1 format the missing contacts
 */

// Some basic security, since it reads a contacts database

  if(isset( $_SERVER['PHP_AUTH_USER'] )) $kotu = $_SERVER['PHP_AUTH_USER'] ;
      else $kotu = "oo" ;
      
  if(isset( $_SERVER['PHP_AUTH_PW'] )) $kotp = $_SERVER['PHP_AUTH_PW'] ;
      else $kotp = "oo" ;
           
	if ( $kotu != "kuser" || $kotp != "kpass") {
    header('WWW-Authenticate: Basic realm="hello:"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'WAAAT';
    exit;
} else {
	
	
 $name = "" ; $f = "" ; $d = "" ; $vcfall = "" ; $uniphone = array() ;
 $phones = array() ; $i = "" ;
 
// FUNCTION converts text to  ENCODING=QUOTED-PRINTABLE, for emails/ names/ etc
   function maken($text) { return str_replace("%", "=", urlencode($text)); }
   
// ARRAY to set proper phone type
   $typAr = array( "0;1"  => ";PREF;CELL",
                  "0;10"  => ";PREF;CELL",
                   "0;4"  => ";CELL",
                     "1"  => ";CELL",
                    "1;"  => ";CELL",
                   "1;0"  => ";PREF;CELL",
                   "1;4"  => ";CELL;VOICE",
                     "2"  => ";FAX;HOME",
                     "3"  => ";PAGER",
				     "4"  => ";PERSONAL;VOICE",
                   "4;0"  => ";VOICE;PREF" 
                      ) ;

?>
<html>
  <head><title>HomeMade! Compaire</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
 </head> <body>

<?php 

// Open the contacts3.db database (Sailfish V3)
   class MyDB extends SQLite3 { function __construct() {
         $this->open('contacts3.db');
      } }   
   $db = new MyDB();  if(!$db) { echo $db->lastErrorMsg(); }

// Open the contacts4.db database (Sailfish V4)
   class NewDB extends SQLite3 { function __construct() {
         $this->open('contacts4.db');
      } }
   $dbn = new NewDB();  if(!$dbn) { echo $dbn->lastErrorMsg(); }
   
// The query that brings all the details   
   $ask = " SELECT  GROUP_CONCAT(P.phoneNumber, '|[]|') AS PHar, GROUP_CONCAT(P.subTypes, '|[]|') STar,
   C.displayLabel, C.firstName, C.lowerFirstName, C.lastName, C.lowerLastName, C.middleName, C.created, C.modified, C.isFavorite,
       C.hasPhoneNumber, C.hasEmailAddress, C.hasOnlineAccount, C.isOnline, C.isDeactivated, C.isIncidental,
	   AD.street, AD.postOfficeBox, AD.region, AD.locality, AD.postCode, AD.country, AD.subTypes,
	   AV.imageUrl, AV.avatarMetadata,  B.birthday,  
	   E.emailAddress, E.lowerEmailAddress, GROUP_CONCAT(NI.nickname) AS nickname, NI.lowerNickname,  NO.note,  O.name, O.title,
	   P.phoneNumber, P.subTypes, P.normalizedNumber
    FROM Contacts AS C   
	   LEFT JOIN Addresses AS AD ON C.contactId =AD.contactId
	   LEFT JOIN Avatars AS AV ON C.contactId =AV.contactId
	   LEFT JOIN Birthdays AS B ON C.contactId =B.contactId
	   LEFT JOIN EmailAddresses AS E ON C.contactId =E.contactId
	   LEFT JOIN Nicknames AS NI ON C.contactId =NI.contactId
	   LEFT JOIN Notes AS NO ON C.contactId =NO.contactId
	   LEFT JOIN Organizations AS O ON C.contactId =O.contactId
	   LEFT JOIN PhoneNumbers AS P ON C.contactId =P.contactId
WHERE 1 = 1 AND C.isDeactivated != 1  GROUP BY firstName, lastName; " ;
   // AND C.lastName = 'SomeName'  <-- add it in the query for specific contact
   $query = $db->query($ask) ; 
     $z = 0;
      while($row = $query->fetchArray(SQLITE3_ASSOC) ) {

   $row['firstName']  = str_replace("'", " ", $row['firstName']) ; 

// Query for contacts4, to find if the contact exists
   $Nask = " SELECT C.contactId, firstName, lastName, phoneNumber FROM Contacts AS C INNER JOIN PhoneNumbers AS P ON C.contactId =P.contactId INNER JOIN Names AS N ON C.contactId =N.contactId 	 WHERE (firstName = '{$row['firstName']}' OR firstName is NULL)  AND (lastName = '{$row['lastName']}' OR lastName is NULL) GROUP BY firstName, lastName ;" ;


   $Nquery = $dbn->query($Nask) ; 
     $Nrow = $Nquery->fetchArray(SQLITE3_ASSOC) ; 

     if(!isset($Nrow["firstName"]) && !isset($Nrow["lastName"]) ) {
		 if(!isset($row["firstName"])) $row["firstName"] = "" ;
		 if(!isset($row["lastName"])) $row["lastName"] = "" ; 
		 $f++ ; 
		 
/*   Debuging echo, if the contact does not exist in the contacts4.db
		 echo "{$row["firstName"]} - {$row["lastName"]} - {$row["PHar"]} DOES NOT EXIST" ; 
		  echo " <br /> \n<strong>{$f}</strong>:  " ;		 
		 echo "<hr />" ; 
*/

// The following part generates the VCARD 2.1 with the details of the contact
$vcfall .= "BEGIN:VCARD\n" ; 
$vcfall .= "VERSION:2.1\n" ; 
$vcfall .= "REV:{$row["created"]}\n" ; 
$vcfall .= "ADR;WORK;PARCEL:;{$row["postOfficeBox"]};{$row["street"]};{$row["locality"]};{$row["region"]};{$row["postCode"]};{$row["country"]}\n" ; 
if(isset($row["birthday"])) $vcfall .= "BDAY:{$row["birthday"]}\n" ; 
$vcfall .= "FN;ENCODING=QUOTED-PRINTABLE:".maken($row["firstName"])." ".maken($row["lastName"])."\n" ; 
$vcfall .= "N;ENCODING=QUOTED-PRINTABLE:".maken($row["nickname"])."\n" ; 
if(isset($row["nickname"])) $vcfall .= "X-NICKNAME:{$row["nickname"]}\n" ; 
if(isset($row["emailAddress"])) $vcfall .= "EMAIL;ENCODING=QUOTED-PRINTABLE:".maken($row["emailAddress"])."\n" ; 

  $phones = explode( "|[]|", $row["PHar"]) ;
  $stypes = explode( "|[]|", $row["STar"]) ;
  
    $tosa = count($phones) ;
// insert phones with their types
   for($i=0;$i<$tosa;$i++) { 
	      if( isset($phones[$i]) && !isset($uniphone[$phones[$i]]) ) {
		     $uniphone[$phones[$i]] = 1;
               if($stypes[$i] == 10) {
                  $vcfall .= "X-ASSISTANT-TEL:{$phones[$i]}\n" ;
                }  else {
              $vcfall .= "TEL{$typAr[$stypes[$i]]}:{$phones[$i]}\n" ; 
            }
        }
     }  // END OF FOR PHONES
  
if(isset($row["Note"])) $vcfall .= "NOTE;ENCODING=QUOTED-PRINTABLE:".maken($row["Note"])."\n" ; 
if(isset($row["Url"])) $vcfall .= "URL;ENCODING=QUOTED-PRINTABLE:".maken($row["Url"])."\n" ; 
$vcfall .= "END:VCARD\n" ;
		  
	   } // END OF IF NOT SET FIRSTNAME LASTNAME
     }  // END OF WHILE 
   
   // echo how many were missing     
   // echo "{$z} - notFound: {$f} Done ..," ; 
   
   // close the databases
   $db->close();
   $dbn->close();
   
  echo $vcfall; // OUTPUT OF THE vCards !
  
   } // END OF ELSE AUTH OK
   
?>
  </body>
 </html>