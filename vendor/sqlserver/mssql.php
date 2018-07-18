<?php
/**
 * sqlserver 操作类
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/15
 * Time: 8:26
 */
class mssql{

    private $dbhost; //数据库主机
    private $dbuser; //数据库用户名
    private $dbpassword; //数据库用户名密码
    private $dbname; //数据库名
    private $dbcharset; //数据库编码，GBK,UTF8,gb2312
    private $conn; //数据库连接标识;
    private $pconn; //是否永久连接
    public $constatus;//是否连接成功

    //构造函数
    function __construct(){
        $this->dbhost = config('mssql.ms_dbhost');
        $this->dbuser = config('mssql.ms_dbuser');
        $this->dbpassword = config('mssql.ms_dbpw');
        $this->dbname = config('mssql.ms_dbname');
        $this->conn = 'conn';
        // $this->pconn = false;
        $this->dbcharset = config('mssql.dbcharset');
        $this->connect();

    }

    //连接数据库
    private function connect(){
        $connectionInfo = array( "Database"=>$this->dbname, "UID"=>$this->dbuser, "PWD"=>$this->dbpassword);
        $this->conn = sqlsrv_connect($this->dbhost, $connectionInfo);
        if(!$this->conn){
            $this->constatus = false;
        }else{
            $this->constatus = true;
        }
    }


    //执行一条SQL语句
    function query($sql) {
        $query = sqlsrv_query ($this->conn , $sql) or die($this->error($sql));
        return $query;
    }

    //创建新的数据库
    function create_database($database) {
        $sql = 'create database ' . $database;
        return $this->query($sql);
    }

    //信息生成数组(双方式)
    function arr($query){
        return sqlsrv_fetch_array($query);
    }

    //获取一条数据。
    function select_one($sql){
        $query=$this->query($sql);
        return $this->assoc($query);
    }

    //信息生成数组(字段名方式)
    function assoc($query) {
        return sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);
    }

    //信息生成数组(数字索引方式)
    function row($query) {
        return sqlsrv_fetch_array($query, SQLSRV_FETCH_NUMERIC);
    }

    //信息生成对象
    function obj($query) {
        return sqlsrv_fetch_object($query);
    }

    //返回数据条数
    function num($table,$where="") {
        $where = $where!="" ?  "where $where" : "";
        return $this->counts("select * from $table $where");
    }

    //返回数据条数
    function counts($sql) {
        return sqlsrv_num_rows($this->query($sql));
    }
    function sql_counts($sql) {
        $count =  $this->select_one($sql);
        return $count[0];
    }

    //返回数据
    function value($table,$row,$where="") {
        $sql="select * from $table where $where";
        $rs=$this->select_one($sql);
        return $rs[$row];
    }

    //插入数据
    function insert($table, $row) {
        $sql = $this->sql_insert($table, $row);
        return $this->query($sql);
    }

    //更新数据
    function update($table,$row,$where) {
        $sql = $this->sql_update($table, $row, $where);
        return $this->query($sql);
    }

    function sql_insert($table, $row){
        $fields = '';
        $values = '';
        foreach ($row as $key=>$value) {
            $fields .= "`".$key."`,";
            $values .= "'".$value."',";
        }
        return "insert into `".$table."` (".substr($fields, 0, -1).") values (".substr($values, 0, -1).")";
    }


    function sql_update($tbname, $row, $where){
        $sqlud='';
        if(is_string($row)){
            $sqlud=$row.' ';
        }else{
            foreach ($row as $key=>$value) {
                $sqlud .= "`$key`"."= '".$value."',";
            }
        }
        return "update `".$tbname."` set ".substr($sqlud, 0, -1)." where ".$where;
    }

    function getarr($sql){
        $getarr = array();
        $query = $this->query($sql);
        while($rs=$this->assoc($query)){
            $getarr[] = $rs;
        }
        return $getarr;
    }

    //删除数据
    function delete($table, $where) {
        return $this->query("delete from $table where $where");
    }


    //获取数据库所有表()
    function gettales() {
        $rs = $this->query("SHOW TABLES FROM ".$this->dbname."");
        $tables = array();
        while ($row = $this->row($rs)) {
            $tables[] = $row[0];
        }
        return $tables;
    }
    //返回表字段数
    function getfields($sql)
    {
        return sqlsrv_num_fields($this->query($sql));
    }

    function restroe($sql)
    {
        return sqlsrv_free_stmt($this->query($sql));
    }
    //错误信息提示
    private function error($sql=""){

        $error = "Mysql_error：".mysql_error()."(".mysql_errno().")";
        $error .= $sql == "" ? "" : "Sql：'{$sql}'";
        return false;
    }
    //析构函数关闭数据库
    function __destruct(){
        sqlsrv_close($this->conn);
    }
}
?>