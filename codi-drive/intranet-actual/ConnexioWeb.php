<?php
/**
* @class ConnexioWeb
* @brief Contó tota la informació relacionada amb la connexió a la base de dades Intranet
*/
class ConnexioWeb {
   public $servidor; /**< string Nom de la maquina on es troba la BD, generalment es localhost */
   public $nomBD; /**< string Nom de la BD  */
   public $nomUsuari; /**< string Nom del usuari autoritzat a entrar a la BD  */
   public $password; /**< string Contrasenyaa de l'usuari */
   public $numRows; /**< string Numero files de la consulta */

   public $connexio; /**< string La connexio a la base de dades. Ex: $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);  */
   public $sentencia; /**< string La consultat a la base de dades. Ex: $result_dates = mysqli_query ($connexio, "SELECT XXX");  */
   private $idpagLockHeld = false;

   /*********************************** FUNCIONS CONSTRUCTORS /***********************************/
   /*
   * @brief Constructor de la classe
   * @return Connexió de la base de dades.
   */
   public function __construct()
   {
      include('parametres-connexio-web.php'); //Fitxer que conté el user, password, server, dbname

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
      $this->sentencia = $this->connexio->prepare($sentenciaSQL);
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

   /**
   * @brief Reserva el següent IDPAG sota un named lock compartit.
   * El lock es manté fins a releaseIdPag() o desconectarBD().
   */
   function reserveIdPag($timeoutSeconds = 10)
   {
      if (!isset($this->connexio) || !($this->connexio instanceof mysqli)) {
         throw new Exception('Cal connectar la BD abans de reservar IDPAG');
      }

      $lockName = 'prisma_inscripcions_idpag_allocator';
      $stmt = $this->connexio->prepare('SELECT GET_LOCK(?, ?)');
      if (!$stmt) {
         throw new Exception('No es pot preparar el lock IDPAG');
      }
      $stmt->bind_param('si', $lockName, $timeoutSeconds);
      $stmt->execute();
      $stmt->bind_result($acquired);
      $stmt->fetch();
      $stmt->close();

      if ((int) $acquired !== 1) {
         throw new Exception('No s\'ha pogut obtenir el lock IDPAG');
      }

      $this->idpagLockHeld = true;

      $stmt = $this->connexio->prepare(
         'SELECT COALESCE(MAX(IDPAG), 0) + 1 FROM inscripcions'
      );
      if (!$stmt) {
         $this->releaseIdPag();
         throw new Exception('No es pot calcular el següent IDPAG');
      }
      $stmt->execute();
      $stmt->bind_result($nextIdPag);
      $stmt->fetch();
      $stmt->close();

      if ((int) $nextIdPag <= 0) {
         $this->releaseIdPag();
         throw new Exception('IDPAG reservat no vàlid');
      }

      return (int) $nextIdPag;
   }

   function releaseIdPag()
   {
      if (!$this->idpagLockHeld || !isset($this->connexio) || !($this->connexio instanceof mysqli)) {
         return;
      }

      $lockName = 'prisma_inscripcions_idpag_allocator';
      $stmt = $this->connexio->prepare('SELECT RELEASE_LOCK(?)');
      if ($stmt) {
         $stmt->bind_param('s', $lockName);
         $stmt->execute();
         $stmt->close();
      }

      $this->idpagLockHeld = false;
   }

   /**
   * @brief Obté el nombre de files afectades en l'última operació MySQL
   * @return Un enter més gran que zero indica el nombre de files afectades o recuperades.
   * El zero indica que no hi ha registres en una actualització amb una sentència UPDATE,
   * que no hi ha files que compleixin la clàusula WHERE de la sentència o que cap
   * consulta ha estat executada encara. -1 indica que la consulta ha retornat un error.
   * Un enter més gran que zero indica el nombre de files afectades o recuperades.
   * El zero indica que no hi ha registres en una actualització amb una sentència UPDATE,
   * que no hi ha files que compleixin la clàusula WHERE de la sentència o que cap consulta
   * ha estat executada encara. -1 indica que la consulta ha retornat un error.
   */
   function affectedRows() {
       return $this->connexio->affected_rows;
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
      $this->releaseIdPag();
      $this->connexio->close();
   }
}
?>
