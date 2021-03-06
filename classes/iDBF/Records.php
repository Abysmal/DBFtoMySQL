<?php
/********************************************
 * DBF-file records Reader
 *
 * Author: Chizhov Nikolay <admin@kgd.in>
 * (c) 2016 CIOB "Inok"
 ********************************************/

namespace iDBF;

class Records {
  private $fp, $headers, $columns, $memo, $encode;
  private $records = 0;

  private $logicals = ['t', 'y', 'д'];

  public function __construct($data, $encode = "utf8", $headers = null, $columns = null) {
    if ($data instanceof Table) {
      $this->headers = $data->getHeaders();
      $this->columns = $data->getColumns();
      $this->fp = $data->getData();
    }
    else {
      if (is_null($headers) || is_null($columns)) {
        throw new \Exception('Not correct data in Record class');
      }
      $this->fp = $data;
      $this->headers = $headers;
      $this->columns = $columns;
    }
    $this->encode = $encode;
    if ($this->headers["memo"] && !is_null($this->headers["memo_file"])) {
      $this->memo = new Memo($this->headers["memo_file"]);
    }
  }

  public function nextRecord() {
    if ($this->records >= $this->headers["records"]) {
      if ($this->memo instanceof Memo) {
        unset($this->memo);
      }
      return false;
    }
    $record = [];
    $data = fread($this->fp, $this->headers["record_length"]);
    $record["deleted"] = (unpack("C", $data[0])[1] == 42);
    $pos = 1;
    foreach ($this->columns as $column) {
      $sub_data = trim(substr($data, $pos, $column["length"]));
      switch($column["type"]) {
        case "F":
        case "N":
          $record[$column["name"]] = (is_numeric($sub_data)) ? (($column["decimal"]) ? (float) $sub_data : (int) $sub_data) : 0;
          break;
        case "T":
        case "D":
          $record[$column["name"]] = ($sub_data != "") ? $sub_data : null;
          break;
        case "L":
          $record[$column["name"]] = (in_array(strtolower($sub_data), $this->logicals));
          break;
        case "C":
          $record[$column["name"]] = $this->convertChar($sub_data);
          break;
        case "M":
          $record[$column["name"]] = ($sub_data != "") ? $this->getMemo((int) $sub_data) : "";
          break;
        case "P":
        case "G":
          $record[$column["name"]] = ($sub_data != "") ? $this->getMemo((int) $sub_data, false) : null;
          break;
      }
      $pos += $column["length"];
    }
    $this->records++;
    return $record;
  }

  private function getMemo($data, $convert = true) {
    $memo = $this->memo->getMemo($data);
    return ($convert) ? $this->convertChar($memo["text"]) : $memo["text"];
  }

  private function convertChar($data) {
    return iconv(str_replace("\r\n", "\n", $this->headers["charset_name"]), $this->encode, $data);
  }
}