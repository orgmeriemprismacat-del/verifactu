<?php
/**
  * @class ConnexioBD
  * @brief Cont� tota la informaci� relacionada amb la connexi� a la base de dades
*/
class ConnexioBBDD {
  public $servidor; /**< string Nom de la maquina on es troba la BD, generalment es localhost */
  public $nomBD; /**< string Nom de la BD  */
  public $nomUsuari; /**< string Nom del usuari autoritzat a entrar a la BD  */
  public $password; /**< string Contrasenyaa de l'usuari */

  public $connexio; /**< string La connexio a la base de dades. Ex: $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);  */
  public $consulta; /**< string La consultat a la base de dades. Ex: $result_dates = mysqli_query ($connexio, "SELECT XXX");  */
  public $resultat; /**< string El resultat de la consula a la base de dades. Ex: $row_dates = mysqli_fetch_array($result_dates)  */
  public $numRows; /**< int El numero de linies del resultat de la consula a la base de dades. Ex: $row_dates = mysqli_fetch_array($result_dates)  */


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
    if(!$this->connexio=mysqli_connect($this->servidor,$this->nomUsuari,$this->password, $this->nomBD))
    {
    	throw new Exception("No es pot connectar: " . mysqli_connect_error());
    }
		mysqli_set_charset($this->connexio, "utf8");
	}

  /*
  * @brief Fer una consulta a la base de dades
  * @param $sentenciaSQL Consulta SQL que es vol fer a la base de dades.
  * @return Realitza la consulta a la base de dades.
	*/
	function consultarBD($sentenciaSQL)
	{
    $this->consulta=mysqli_query($this->connexio,$sentenciaSQL);
		$this->numRows = $this->consulta->num_rows;
  }

  /*
  * @brief Obtenir el resultat de la consulta de la base de dades
  * @return $resultat Obt� el resultat de la base de dades una vegada feta la consulta.
	*/
	function obtenirResultat()
	{
    $this->resultat=mysqli_fetch_array($this->consulta);
    return $this->resultat;
  }

  /*
  * @brief Obtenir el resultat de la consulta de la base de dades
  * @return $resultat Obt� el resultat de la base de dades una vegada feta la consulta.
	*/
	function obtenirNumRows()
	{
    return $this->numRows;
  }

  /*
  * @brief Lliurar la consulta
  * @return Lliura la consulta.
	*/
	function lliurarConsulta()
	{
    mysqli_free_result($this->consulta);
	}

  /*
  * @brief Actualitzaci� d'alguns registres de la BD
  * @param $sentenciaSQL Actualitzaci� SQL que es vol fer a la base de dades.
  * @return Actualitza els registres a la BD.
	*/
	function actualitzarRegistre($sentenciaSQL)
	{
    if(!mysqli_query($this->connexio,$sentenciaSQL)) {
    	throw new Exception("Problema al actualitzar el registre: " . mysqli_error($this->connexio));
    }
	}

  /*
  * @brief Desconnexi� de la base de dades
  * @return Es desconnecta la connexi� a la BD.
	*/
	function desconectarBD()
	{
    mysqli_close($this->connexio);
	}
}
?>
