<?php
/**
  * @class ConnexioBD
  * @brief Cont� tota la informaci� relacionada amb la connexi� a la base de dades
*/
class ConnexioBBDDSTMT {
   public $servidor; /**< string Nom de la maquina on es troba la BD, generalment es localhost */
   public $nomBD; /**< string Nom de la BD  */
   public $nomUsuari; /**< string Nom del usuari autoritzat a entrar a la BD  */
   public $password; /**< string Contrasenyaa de l'usuari */

   public $connexio; /**< string La connexio a la base de dades. Ex: $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);  */
   public $sentencia; /**< string La consultat a la base de dades. Ex: $result_dates = mysqli_query ($connexio, "SELECT XXX");  */


   /*********************************** FUNCIONS CONSTRUCTORS /***********************************/
   /*
   * @brief Constructor de la classe
   * @return Connexi� de la base de dades.
   */
   public function __construct()
   {
      include('parametres_connexio.php'); //Fitxer que conté el user, password, server, dbname

      $this->servidor=$servidor;
      $this->nomBD=$bbdd;
      $this->nomUsuari=$usuari;
      $this->password=$pw;
      $this->numRows=0;
   }

   /*
   * @brief Connexi� a la base de dades
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
   * @brief Inicia una transacció explícita sobre la connexió legacy.
   */
   function beginTransaction()
   {
      if (!$this->connexio->begin_transaction()) {
         throw new Exception('No es pot iniciar la transacció');
      }
   }

   /*
   * @brief Confirma la transacció activa.
   */
   function commitTransaction()
   {
      if (!$this->connexio->commit()) {
         throw new Exception('No es pot confirmar la transacció');
      }
   }

   /*
   * @brief Reverteix la transacció activa. S'utilitza també en vies d'error.
   */
   function rollbackTransaction()
   {
      if (isset($this->connexio) && $this->connexio) {
         $this->connexio->rollback();
      }
   }

   /*
   * @brief Reserva de manera serialitzada el següent IDPAG de les inscripcions.
   * @return Retorna el següent IDPAG mantenint un named lock fins a releaseIdPag().
   */
   function reserveIdPag($timeoutSeconds = 10)
   {
      $lockName = 'prisma_inscripcions_idpag_allocator';

      $stmt = $this->connexio->prepare('SELECT GET_LOCK(?, ?)');
      if (!$stmt) {
         throw new Exception('No es pot preparar el lock IDPAG');
      }
      $stmt->bind_param('si', $lockName, $timeoutSeconds);
      $stmt->execute();
      $stmt->bind_result($locked);
      $stmt->fetch();
      $stmt->close();

      if ((int) $locked !== 1) {
         throw new Exception('No s\'ha pogut reservar IDPAG');
      }

      $stmt = $this->connexio->prepare('SELECT COALESCE(MAX(IDPAG), 0) + 1 FROM inscripcions');
      if (!$stmt) {
         $this->releaseIdPag();
         throw new Exception('No es pot calcular el següent IDPAG');
      }
      $stmt->execute();
      $stmt->bind_result($idPag);
      $stmt->fetch();
      $stmt->close();

      if (!is_numeric($idPag) || (int) $idPag <= 0) {
         $this->releaseIdPag();
         throw new Exception('IDPAG reservat no vàlid');
      }

      return (int) $idPag;
   }

   /*
   * @brief Allibera el named lock compartit per a l'allocator IDPAG.
   */
   function releaseIdPag()
   {
      if (!isset($this->connexio) || !$this->connexio) {
         return;
      }

      $lockName = 'prisma_inscripcions_idpag_allocator';
      $stmt = $this->connexio->prepare('SELECT RELEASE_LOCK(?)');
      if (!$stmt) {
         return;
      }
      $stmt->bind_param('s', $lockName);
      $stmt->execute();
      $stmt->close();
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
   * @brief Desconnexi� de la base de dades
   * @return Es desconnecta la connexi� a la BD.
   */
   function desconectarBD()
   {
      // mysqli_close($this->connexio);
      $this->connexio->close();
   }
}
?>
