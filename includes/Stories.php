<?php

if (!defined('ABSPATH')) {
    exit;
}

class Stories
{
    private $identifier;
    private $title;
    private $created_at;
    private $updated_at;

    function __construct($identifier, $title, $created_at, $updated_at)
    {
        $this->identifier = $identifier;
        $this->title = $title;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
    }

    function get_identifier()
    {
        return $this->identifier;
    }

    function get_title()
    {
        return $this->title;
    }

    function get_created_at()
    {
        return $this->created_at;
    }

    function get_updated_at()
    {
        return $this->updated_at;
    }

    function set_identifier($identifier)
    {
        $this->identifier = $identifier;
    }

    function set_title($title)
    {
        $this->title = $title;
    }

    function set_created_at($created_at)
    {
        $this->created_at = $created_at;
    }

    function set_updated_at($updated_at)
    {
        $this->updated_at = $updated_at;
    }
}
