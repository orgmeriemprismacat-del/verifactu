<?php
/**
* @class ConnexioIntranet
* @brief Contó tota la informació relacionada amb la connexió a la base de dades Intranet
*/
class ConnexioIntranet {
   public $servidor; /**< string Nom de la maquina on es troba la BD, generalment es localhost */
   public $nomBD; /**< string Nom de la BD  */
   public $nomUsuari; /**< string Nom del usuari autoritzat a entrar a la BD  */
   public $password; /**< string Contrasenyaa de l'usuari */
   public $numRows; /**< string Numero files de la consulta */

   public $connexio; /**< string La connexio a la base de dades. Ex: $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);  */
   public $sentencia; /**< string La consultat a la base de dades. Ex: $result_dates = mysqli_query ($connexio, "SELECT XXX");  */

   /*********************************** FUNCIONS CONSTRUCTORS /***********************************/
   /*
   * @brief Constructor de la classe
   * @return Connexió de la base de dades.
   */
   public function __construct()
   {
      include('parametres-connexio-intranet.php'); //Fitxer que conté el user, password, server, dbname

      $this->servidor=$servidor;
      $this->nomBD=$bbdd;
      $this->nomUsuari=$usuari;
      $this->password=$pw;
      $this->numRows=0;
   }

   /*
   * @brief Connexió a la base de dades
   * @return S'ha connectat a la base de dades.
   */
   public function connectarBD()
   {
      /* Obrim la connexió */
      $this->connexio = new mysqli($this->servidor,$this->nomUsuari,$this->password, $this->nomBD);

      /* Comprovem la connexió */
      if (mysqli_connect_errno()) {
         throw new Exception("No es pot connectar: " . mysqli_connect_error());
      }

      $this->connexio->set_charset("utf8");
   }

   /*
   * @brief Fer una consulta a la base de dades
   * @param $sentenciaSQL Consulta SQL que es vol fer a la base de dades.
   * @return Realitza la consulta a la base de dades.
   */
   function consultarBD($sentenciaSQL)
   {
      $this->sentencia =$this->connexio->prepare($sentenciaSQL);
      return $this->sentencia;
   }

   /*
   * @brief Fer una consulta a la base de dades
   * @param $sentenciaSQL Consulta SQL que es vol fer a la base de dades.
   * @return Realitza la consulta a la base de dades.
   */
   function prepare($sentenciaSQL)
   {
      $this->sentencia =$this->connexio->prepare($sentenciaSQL);
      return $this->sentencia;
   }

   /*
   * @brief Torna el id de l'ultim registre inserit
   * @return Retorna el id de l'ultim registre inserit.
   */
   function lastInsertId()
   {
      return $this->connexio->insert_id;
   }

   /*
   * @brief Tanca la sessió de la base de dades
   * @return Tanca la sessió de la base de dades
   */
   function closeStmt()
   {
      $this->sentencia->close();
   }

   /*
   * @brief Desconnexió de la base de dades
   * @return Es desconnecta la connexió a la BD.
   */
   function desconectarBD()
   {
      $this->connexio->close();
   }
}
?>
