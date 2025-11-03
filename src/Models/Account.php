<?php
namespace BiwengerProManagerAPI\Models;

class Account implements \JsonSerializable
{
    private $id;
    private $token;
    private $name;
    private $email;
    private $phone;
    private $locale;
    private $birthday;
    private $status;
    private $credits;
    private $created;
    private $newsletter;
    private $unreadMessages;
    private $lastAccess;
    private $source;
    private $devices;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->token = $data['token'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->email = $data['email'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->locale = $data['locale'] ?? null;
        $this->birthday = $data['birthday'] ?? null;
        $this->status = $data['status'] ?? null;
        $this->credits = $data['credits'] ?? null;
        $this->created = $data['created'] ?? null;
        $this->newsletter = isset($data['newsletter']) ? (bool)$data['newsletter'] : null;
        $this->unreadMessages = isset($data['unreadMessages']) ? (bool)$data['unreadMessages'] : null;
        $this->lastAccess = $data['lastAccess'] ?? null;
        $this->source = $data['source'] ?? null;
        $this->devices = $data['devices'] ?? [];
    }

    public function getId()
    {
        return $this->id;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'token'=> $this->token,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'locale' => $this->locale,
            'birthday' => $this->birthday,
            'status' => $this->status,
            'credits' => $this->credits,
            'created' => $this->created,
            'newsletter' => $this->newsletter,
            'unreadMessages' => $this->unreadMessages,
            'lastAccess' => $this->lastAccess,
            'source' => $this->source,
            'devices' => $this->devices
        ];
    }
}