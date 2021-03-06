<?php
namespace CSV;

class Query {

    private $file;
    private $filter = null;
    private $transform = null;
    private $headers = array();
    private $outpath = "php://output";
    private $select = "*";
    
    private $limit = 0;
    private $count = 0;
    private $line_count = 0;

    /**
     *  constructor; calls CSV\Query::from() loader method
     *
     */

    public function __construct($source = null) {
        if ( $source ) { $this->from($source); }
        return $this;
    }

    /**
     *  getter methods - handy for post-conversion checking
     */

    public function getCount($includeHeaders = false) { return $this->count + (int) $includeHeaders; }
    public function getFilter() { return $this->filter; }
    public function getHeaders() { return $this->select == "*" ? $this->headers : $this->select; }
    public function getLimit() { return $this->limit; }
    public function getLineCount() { return $this->line_count; }
    public function getOutputPath() { return $this->outpath; }
    public function getRawHeaders() { return $this->headers; }
    public function getTransform() { return $this->transform; }

    /**
     *  executes the parsing of input csv + writing of output csv
     *
     *  
     */

    public function execute() {
        $from = $this->file;
        $to = isset($this->outpath) ? fopen($this->outpath, "w") : null;
        $headers = $this->headers;
        $filter = $this->filter;
        $transform = $this->transform;
        $select = isset($this->select) ? $this->select : "*";

        $getRows = array();

        if ( !isset($to) ) { throw new \Exception("No output path provided"); }

        // handle select input
        if ( $select == "*" ) {
            $getRows = array_keys($headers);
            fputcsv($to, $headers);
        } else {
            $outCols = array();

            for ($i = 0; $i < count($headers); $i++ ) {
                if ( in_array($headers[$i], $select) ) {
                    array_push($getRows, $i);
                    array_push($outCols, $headers[$i]);
                }
            }

            fputcsv($to, $outCols);
        }

        while( $row = fgetcsv($from) ) {
            $this->line_count++;

            $rowOut = array();

            if ( is_callable($filter) ) {
                $row_arr = array_combine($headers, $row);
                if ( !$filter($row_arr) ) {
                    continue;
                }
            }

            if ( is_callable($transform) ) {
                $row_arr = array_combine($headers, $row);
                $transform($row_arr);
                $row = array_values($row_arr);
            }

            foreach( $getRows as $colNum ) {
                array_push($rowOut, $row[$colNum]);
            }

            fputcsv($to, $rowOut);

            $this->count++;
            if ( $this->limit && $this->count == $this->limit ) { break; }
        }
    }

    /**
     *  alias for CSV\Query::where()
     *
     *  @param  callable   filter callable, takes single param of 
     *                     associative array row w/ headers as keys + row's values
     *  @return CSV\Query  this instance
     */

    public function filter($filter = null) {
        return $this->where($filter);
    }

    /**
     *  loads a file + header array
     *
     *  @param  string                      filepath
     *  @return CSV\Query                   this instance
     *  @throws \InvalidArgumentException
     */

    public function from($path) {
       if ( file_exists($path) ) {
            $this->file = fopen($path, "r");
            $this->headers = fgetcsv($this->file);
        } else {
            // handle bad source
            throw new \InvalidArgumentException("Source needs to be a file");
        }
    }

    /**
     *  set a limit for number of lines returned
     *
     *  @param int
     *  @return CSV\Query  this instance
     */

    public function limit($limit = 0) {
        $this->limit = $limit;
        return $this;
    }

    /**
     *  select rows to return
     *
     *  @param  string|array  "*", comma-delimited list, or array
     *  @return CSV\Query     this instance
     */

    public function select($which = "*") {
        $this->select = $which;
        return $this;
    }

    /**
     *  adds output location (defaults to stdout)
     *
     *  @param  string    file location
     *  @return CSV\Query this instance
     */

    public function to($location = "php://output") {
        $this->outpath = $location;
        return $this;
    }

    public function transform($callable = null) {
        $this->transform = $callable;
        return $this;
    }

    /**
     *  applies filter to query
     *
     *  @param  callable   filter callable, takes single param of 
     *                     associative array row w/ headers as keys + row's values
     *  @return CSV\Query  this instance
     */

    public function where($filter = null) {
        if ( !is_callable($filter) ) {
            $filter = null;
        }

        $this->filter = $filter;
        return $this;
    }
}