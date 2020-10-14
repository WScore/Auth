<?php
namespace WScore\Auth\RememberAdaptor;

use PDO;
use WScore\Auth\RememberMeInterface;

class RememberMe implements RememberMeInterface
{
    /**
     * @var Pdo
     */
    private $pdo;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $id_name;

    /**
     * @var string
     */
    protected $token_name;

    /**
     * @param Pdo $pdo
     */
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }
    
    /**
     * verifies if the $id and token are in remembrance.
     *
     * @param string $loginId
     * @param string $token
     * @return bool
     */
    public function verifyRemember($loginId, $token)
    {
        $found = $this->getRemembered($loginId, $token);
        if ($found) {
            return true;
        }
        return false;
    }

    /**
     * returns remember token. tokens maybe newly generated one
     * if this is the first time to remember, or return the
     * existing token saved from previous session.
     *
     * set false not to use remember-me.
     *
     * @param string      $loginId
     * @param string|null $token
     * @return bool|string
     */
    public function generateToken($loginId, $token)
    {
        $found = $this->getRemembered($loginId, $token);
        if ($found) {
            return $found['token'];
        }
        $token = $this->calRememberToken();
        $this->saveIdWithToken($loginId, $token);
        return $token;
    }

    /**
     * saves $id and $token as remembered. 
     * overwrite this method to save more information. 
     * 
     * @param string $id
     * @param string $token
     * @return bool
     */
    protected function saveIdWithToken($id, $token)
    {
        $stmt  = $this->pdo->prepare("
          INSERT {$this->table} 
            ($this->id_name}, {$this->token_name})
          VALUES( ?, ? )
        ");
        return $stmt->execute([$id, $token]);
    }

    /**
     * get remembered data for $id and $token.
     * 
     * @param string $id
     * @param string $token
     * @return array
     */
    private function getRemembered($id, $token)
    {
        $stmt  = $this->pdo->prepare("
          SELECT
            {$this->id_name} AS user_id, 
            {$this->token_name} AS token
          FROM {$this->table}
          WHERE {$this->id_name}=? AND {$this->token_name}=?
        ");
        $found = $stmt->execute([$id, $token]);
        if ($found && is_array($found) && count($found) === 1) {
            return $found[0];
        }
        return [];
    }
    
    /**
     * calculates a random string for new remember token.
     *
     * @return string
     */
    private function calRememberToken()
    {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }
}

