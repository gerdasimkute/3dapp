<?php

class Queries
{
    private $database;

    public $upsert_file_statement;
    public $upsert_post_statement;

    public $select_file_statement;
    public $select_post_statement;

    public $select_files_statement;
    public $select_posts_statement;

    public function prepare($string)
    {
        return $this->database->prepare($string);
    }

    public function __construct($connection_string, $file_table_name, $post_table_name)
    {
        try {
            $this->database = new PDO($connection_string);
            $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->files = $file_table_name;
            $this->posts = $post_table_name;
        } catch (Exception $e) {
            throw new Exception("Could not connect to the database", 601, $e);
        }

        try {
            $create_statement = $this->prepare(sprintf("
                CREATE TABLE IF NOT EXISTS %s(
                    key INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    image_path TEXT,
                    model_path TEXT
                );", $file_table_name));
            $create_statement->execute();
            $create_statement = $this->prepare(sprintf("
                CREATE TABLE IF NOT EXISTS %s(
                    key INTEGER PRIMARY KEY,
                    name TEXT NOT NULL,
                    data JSON
                );", $post_table_name));
            $create_statement->execute();
        } catch (Exception $e) {
            throw new Exception("Could not initialise table on the database", 602, $e);
        }

        $this->upsert_file_statement = $this->prepare(sprintf("
            INSERT INTO %s(name,image_path,model_path)
                VALUES(:name,:image,:model);", $file_table_name));

        $this->upsert_post_statement = $this->prepare(sprintf("
            INSERT INTO %s(name,data)
                VALUES(:name,:data);", $post_table_name));

        $select_statement = "
            SELECT * FROM %s WHERE key=:key;";
        $this->select_file_statement = $this->prepare(sprintf($select_statement, $file_table_name));
        $this->select_post_statement = $this->prepare(sprintf($select_statement, $post_table_name));

        $this->select_files_statement = $this->prepare(sprintf("
            SELECT key, name, image_path FROM %s;
        ", $file_table_name));
        $this->select_posts_statement = $this->prepare(sprintf("
            SELECT key, data->>'$.name' as name, data->>'$.text' as text, data->>'$.image' as image, data->>'$.visible' as visible FROM %s;
        ", $post_table_name));
    }

}

function initialise_PDO()
{
    $db_file = 'database/models.db';
    // it appears that an empty file is not a valid sqlite database
    // if (!file_exists($db_file)) {
    //     try {
    //         fopen($db_file, 'w+');
    //     } catch (Exception $e) {
    //         throw new Exception("Could not open database file", 602, $e);
    //     }
    // }
    $string = 'sqlite:' . __DIR__ . '/' . $db_file;
    return new Queries($string, "FILES_TABLE", "POSTS_TABLE");
}
