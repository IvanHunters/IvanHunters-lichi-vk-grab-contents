<?php


namespace Lichi\Grab\Post;
use mysqli;

class MysqliProvider
{
    private mysqli $mysqli;

    function __construct($user, $password, $database = false, $host = false){

        if(!$database) $database = $user;
        if(!$host) $host = "127.0.0.1";

        $this->mysqli = new mysqli($host, $user, $password, $database);

        mysqli_query($this->mysqli,"SET character_set_client='utf8mb4'");
        mysqli_query($this->mysqli,"SET character_set_connection='utf8mb4'");
        mysqli_query($this->mysqli,"SET character_set_results='utf8mb4'");

    }

    public function select(string $table, array $where): array
    {
        $whereString = $this->configWhere($where);
        return $this->exq("SELECT * FROM {$table} WHERE {$whereString}");
    }

    public function insert(string $table, array $fields): array
    {
        $fieldsString = $this->configPlaces($fields);
        return $this->exq("INSERT INTO {$table} SET {$fieldsString}");
    }

    public function update(string $table, array $fields, array $where)
    {
        $fieldsString = $this->configPlaces($fields);
        $whereString = $this->configWhere($where);

        return $this->exq("UPDATE {$table} SET {$fieldsString} WHERE {$whereString}");
    }

    private function configWhere(array $where): string
    {
        $whereAnswer = [];
        foreach ($where as $name => $value)
        {
            $whereAnswer[] = sprintf("%s = '%s'", $name, $value);
        }
        return implode(" and ", $whereAnswer);
    }

    private function configPlaces(array $where): string
    {
        $whereAnswer = [];
        foreach ($where as $name => $value)
        {
            $whereAnswer[] = sprintf(" %s = '%s'", $name, $value);
        }
        return implode(",", $whereAnswer);
    }



    function exq($query, $flag = false): array
    {

        $result = $this->mysqli->query($query);
        if($flag || preg_match("/delete|update/imu", $query)) return [$result];

        if(preg_match("/insert/imu", $query))
        {
            return [
                'insert_id'=>(int) $this->mysqli->insert_id
            ];
        }

        $resultData = $result->fetch_assoc();
        $resultData['count_rows'] = $result->num_rows;

        return $resultData;

    }
}