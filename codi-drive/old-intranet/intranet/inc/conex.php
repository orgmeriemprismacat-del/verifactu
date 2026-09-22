<?php 
function Conectarse() 
{ 
   if (!($link=mysql_connect("localhost","suport","1324GiRoNa"))) 
   { 
      echo "Error connectant a la base de dades de PrisMa."; 
      exit(); 
   } 
   if (!mysql_select_db("gestio",$link)) 
   { 
      echo "Error a la base de dades de PrisMa."; 
      exit(); 
   } 
   return $link; 
} 

function Conectarse2() 
{ 
   if (!($link=mysql_connect("localhost","darrer","6rid.rpeH-m2pgeYBF-P"))) 
   { 
      echo "Error connectant a la base de dades del Campus."; 
      exit(); 
   } 
   if (!mysql_select_db("noumoodle",$link)) 
   { 
      echo "Error a la base de dades del Campus."; 
      exit(); 
   } 
   return $link; 
} 




?>
