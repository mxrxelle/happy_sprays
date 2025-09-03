<?php
class Database {

    // 🔗 Connection
    function opencon() {
        try {
            $con = new PDO(
                "mysql:host=localhost;dbname=happy_sprays",
                "root",
                ""
            );
        }
    }
}