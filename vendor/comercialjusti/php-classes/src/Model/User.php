<?php 

namespace Just\Model;

use \Just\DB\Sql;
use \Just\Model;
use \Just\Mailer;

class User extends Model {

	const SESSION = "User";//nome da sessão
	const SECRET = "ComercialJustiRecovery";
	//validando se existe um usuario e trazendo o hash e verificando se o hash é compativel com a senha enviada
	public static function login($login, $password)
	{

		$sql = new Sql();
		//evitar o sql injection =:LOGIN
		$results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :LOGIN", array(
			":LOGIN"=>$login
		));
		//Não encontrando 
		if (count($results) === 0)
		{//usa essa \ no Exception por que não criamos as nossa exeções, dai coloca ela para ir ao diretorio principal
			throw new \Exception("Usuário inexistente ou senha inválida.");
		}

		$data = $results[0];
		//verificar a senha do usuário, recebendo a senha e o hash do banco
		if (password_verify($password, $data["despassword"]) === true)
		{

			$user = new User();
			//metodo magico
			$user->setData($data);
			//para usar um login cria-se uma sessão, caso n exista essa seção ela redireciona para a pagina de login
			$_SESSION[User::SESSION] = $user->getValues();//trazer de detro do obejto quais são os seu valores

			return $user;

		} else {
			throw new \Exception("Usuário inexistente ou senha inválida");
		}

	}
	//metodo para verificação de ligin no adm
	public static function verifyLogin($inadmin = true)// inadmin serve para verificar se ele e adm ou não para ter acesso ao administrativo
	{
		//valida se ela n existir. Sendo falsa ela redireciona para o login
		if (
			!isset($_SESSION[User::SESSION])
			||
			!$_SESSION[User::SESSION]
			||
			!(int)$_SESSION[User::SESSION]["iduser"] > 0//Verifica se o usuario não for maior q 0
			||
			(bool)$_SESSION[User::SESSION]["inadmin"]!= $inadmin//verificação para ver se tem acesso a administracção também
		) {

			header("Location: /admin/login");
			exit;

		}

	}

	public static function logout()
	{

		$_SESSION[User::SESSION] = NULL;

	}
	//Metodo para trazer todos os ususario.
	public static function listALL()
	{

		$sql = new Sql();

		return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.idperson");

	}
	//Metodo para salvar um novo usuario
	public function save()
	{

		$sql = new Sql();
		//Procedure para salvar no banco e retornar, e usar a ordem q foi feito na procedure
		$results = $sql->select("CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
			":desperson"=>utf8_decode($this->getdesperson()),
			":deslogin"=>$this->getdeslogin(),
			":despassword"=>User::getPasswordHash($this->getdespassword()),
			":desemail"=>$this->getdesemail(),
			":nrphone"=>$this->getnrphone(),
			":inadmin"=>$this->getinadmin()
		));

		$this->setData($results[0]);

	}
	//metodo para receber e setar os dados do usuario
	public function get($iduser)
	{

		$sql = new Sql();

		$results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser", array(
			":iduser"=>$iduser
		));

		$this->setData($results[0]);

	}
	//metodo para atulizar cadastro
	public function update()
	{

		$sql = new Sql();

		$results = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
			":iduser"=>$this->getiduser(),
			":desperson"=>$this->getdesperson(),
			":deslogin"=>$this->getdeslogin(),
			":despassword"=>$this->getdespassword(),
			":desemail"=>$this->getdesemail(),
			":nrphone"=>$this->getnrphone(),
			":inadmin"=>$this->getinadmin()
		));

		$this->setData($results[0]);		

	} 

	public function delete()
	{

		$sql = new Sql();

		$sql->query("CALL sp_users_delete(:iduser)", array(
			":iduser"=>$this->getiduser()
		));

	}

	public static function getForgot($email)
	{

		$sql = new Sql();

		$results = $sql->select("
			SELECT *
			FROM tb_persons a
			INNER JOIN tb_users b USING(idperson)
			WHERE a.desemail = :email;
		", array(
			":email"=>$email
		));

		if (count($results) === 0)
		{
			throw new \Exception("Não foi possivel recuperar a senha.");

		}
		else
		{

			$data = $results[0];

			$results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
				":iduser"=>$data['iduser'],
				":desip"=>$_SERVER['REMOTE_ADDR']
			));

			if(count($results2) === 0)
			{

				throw new \Exception("Não foi possivel recuperar a senha");
				
			}
			else
			{

				$dataRecovery = $results2[0];

				$code = openssl_encrypt($dataRecovery['idrecovery'], 'AES-128-CBC', pack("a16", User::SECRET), 0, pack("a16", User::SECRET));

				$code = base64_encode($code);

				$link = "http://www.comercialjusti.com.br/admin/forgot/reset?code=$code";

				$mailer = new Mailer($data["desemail"], $data["desperson"], "Redefinir Senha do Comercial Justi", "forgot", array(
					"name"=>$data["desperson"],
					"link"=>$link
				));

				$mailer->send();

				return $data;

			}

		}

	}

	public static function validForgotDecrypt($code)
	{

		$idrecovery = openssl_decrypt(base64_decode($code), 'AES-128-CBC', pack("a16", User::SECRET), 0, pack("a16", User::SECRET));

		$sql = new Sql();

		$results = $sql->select("
			SELECT *
			FROM tb_userspasswordsrecoveries a
			INNER JOIN tb_users b USING(iduser)
			INNER JOIN tb_persons c USING(idperson)
			WHERE 
				a.idrecovery = :idrecovery
				AND
				a.dtrecovery IS NULL
				AND
				DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();

		", array(
			":idrecovery"=>$idrecovery
		));

		if (count($results) === 0)
		{
			throw new \Exception("Não foi possivel recuperar a senha");
		}
		else
		{

			return $results[0];

		}

	}

	public static function setForgotUsed($idrecovery)
	{

		$sql = new Sql();

		$sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(
			":idrecovery"=>$idrecovery
		));

	}

	public function setPassword($password)
	{

		$sql = new Sql();

		$sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
			":password"=>$password,
			":iduser"=>$this->getiduser()
		));

	}

	public static function getPasswordHash($password)
	{

		return password_hash($password, PASSWORD_DEFAULT, [
			'cost'=>12
		]);

	}

}

 ?>