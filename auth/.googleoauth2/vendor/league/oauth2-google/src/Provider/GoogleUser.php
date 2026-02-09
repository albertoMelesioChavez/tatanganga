<?php

namespace League\OAuth2\Client\Provider;

class GoogleUser implements ResourceOwnerInterface
{
    /**
     * @var array
     */
    protected $response;

    /**
     * @param array $response
     */
    public function __construct(array $response)
    {
        $this->response = $response;
    }

    public function getId()
    {
        return $this->response['id'];
    }

    /**
     * Get preferred display name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->response['name'];
    }

    /**
     * Get preferred first name.
     *
     * @return string
     */
    public function getFirstName()
    {
        return $this->response['given_name'];
    }

    /**
     * Get preferred last name.
     *
     * @return string
     */
    public function getLastName()
    {
        return $this->response['family_name'];
    }

    /**
     * Get email address.
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->response['email'];
    }

    /**
     * Get avatar image URL.
     *
     * @return string|null
     */
    public function getAvatar()
    {
        return $this->response['picture'];
    }

    /**
     * Get user data as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->response;
    }
}
