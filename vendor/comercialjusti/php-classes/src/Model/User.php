<?php 

namespace Just\Model;

use \Just\DB\Sql;
use \Just\Model;

class User extends Model {

	const SESSION = "User";

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

}

 ?>