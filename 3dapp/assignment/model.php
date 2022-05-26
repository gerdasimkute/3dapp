<?php

require_once 'vendor/autoload.php';
require_once 'streams.php';

class Model
{

    private $spa_template;
    private $spa_pages;
    private $pdo;

    private function send($object)
    {
        return stripslashes(json_encode(($object),  JSON_PRETTY_PRINT));
    }

    //https://www.slimframework.com/docs/v3/cookbook/uploading-files.html
    private function move($directory, $uploadedFile)
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $basename = bin2hex(random_bytes(8)); // see http://php.net/manual/en/function.random-bytes.php
        $filename = sprintf('%s.%0.8s', $basename, $extension);

        $uploadedFile->moveTo(__DIR__ . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }

    private function upsert_file($name, $image, $model)
    {
        $statement = $this->pdo->upsert_file_statement;
        try {
            $statement->execute([
                ':name' => $name,
                ':image' => $image,
                ':model' => $model
            ]);
            return null;
        } catch (Exception $e) {
            return $e;
        }
    }

    private function upsert_post($name, $data)
    {
        $statement = $this->pdo->upsert_post_statement;
        try {
            $statement->execute([
                ':name' => $name,
                ':data' => $data,
            ]);
            return null;
        } catch (Exception $e) {
            return $e;
        }
    }

    public function insert_file($request)
    {
        $uploaded = $request->getUploadedFiles();
        $body = $request->getParsedBody();
        $folder = 'database/uploads';

        $result = [
            'message' => 'So, unfortunately, only after fully implementing this functionality I realised just how unreliable file upload would work on the uni server.',
            'Passive Aggressive part' => 'The models were added manually to the database. You may not add aditional ones.', 
            'explanation' => 'As you can see below, regardless of whether you have added the files on the previous form or not, the following PHP global is empty.',
            '$_FILES' => $_FILES,
            'a joke for my sanity' => "I'm tremendously upsert about this."
        ];
        return $this->send($result);

        $name = $body['name'];
        $image = null;
        $model = null;
        if (!$uploaded['image'] != null)
            $image = $this->move($folder, $uploaded['image']);
        if (!$uploaded['image'] != null)
            $model = $this->move($folder, $uploaded['model']);
        if ($e = $this->upsert_file($name, $image, $model)) {
            $result = [
                "result" => "file upsert unsuccessful",
                "message" => $e->getMessage(),
                "debug" => var_export($body)
            ];
            return $this->send($result);
        }
        $result = [
            "result" => "upsert successful",
            "image" => $image,
            "model" => $image
        ];
        return $this->send($result);
    }
    public function insert_post($request)
    {
        $body = $request->getParsedBody();
        $name = $body["name"];
        $data = json_encode($body);
        if ($e = $this->upsert_post($name, $data)) {
            $result = [
                "result" => "post upsert unsuccessful",
                "message" => $e->getMessage(),
                "debug" => var_export($body)
            ];
            return $this->send($result);
        }
        $result = [
            "result" => "upsert successful"
        ];
        return $this->send($result);
    }

    private function fetch($statement, $key)
    {
        try {
            $statement->execute([':key' => $key]);
            $result = ($statement->fetch(PDO::FETCH_OBJ));
        } catch (Exception $e) {
            $result = var_export($e);
        }
        return $result;
    }

    public function select_file($key)
    {
        $result = $this->fetch($this->pdo->select_file_statement, $key);
        return $this->send($result);
    }

    public function select_post($key)
    {
        $result = $this->fetch($this->pdo->select_post_statement, $key);
        return $this->send($result);
    }

    private function fetchAll($statement)
    {
        try {
            $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_OBJ);
        } catch (Exception $e) {
            $result = var_export($e);
        }
        return $result;
    }

    public function list_files()
    {
        $result = $this->fetchAll($this->pdo->select_files_statement);
        return $this->send($result);
    }
    public function list_posts()
    {
        $result = $this->fetchAll($this->pdo->select_posts_statement);
        return $this->send($result);
    }

    public function __construct()
    {
        $this->pdo = initialise_PDO();
        // html is used for page template
        $this->spa_template = 'content/view.html';
        // here we have a dilemma
        // we either construct the entire page
        // or we let the client fetch()
        // the inner html after loading the view
        // I have decided to include all fragments
        // with the initial request with reasoning
        // that performing an http request has 
        // more overhead than string concatenation
        // htm is used for html fragments
        $this->spa_pages = [
            'content/pages/index.htm',
            'content/pages/home.htm',
            'content/pages/project.htm',
            'content/pages/about.htm'
        ];
        // it would be trivial to convert this
        // to only serve the view
        // and let it fetch the inner html
        // exercise is left to the reader
        $this->single_page_content = $this->load_template($this->spa_template, $this->spa_pages);
    }

    private function load_template($template_name, $page_names)
    {
        $template = load_file_stream($template_name);
        foreach ($page_names as &$page) {
            $template = do_replacement($template, load_file_stream($page));
        }
        return $template;
    }

    public function spa_page()
    {
        return $this->single_page_content;
    }
}
