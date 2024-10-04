<?php
/**
 * 1. take filename as input
 * 2. use basename choose the mapping template
 * 3. read the file
 * 4. map each row to the ProductModel()
 * 5. save the ProductModel() to the export file
 */
class Commonize_Import_Data
{

    private $_inputFile;
    private $_outputFile;
    private $_mappingTemplate;
    private $_data;
    private $_totalRows = 0;
    private $_completedRows = 0;
    private $_emptyRows = 0;
    private $_incorrectRows = 0;
    private $_start_time;
    private $_end_time;
    
    /**
     * Constructor function
     */
    public function __construct($args, $assoc_args)
    {
        $this->_start_time = microtime(true);
        $this->_inputFile = $args;
        $this->_outputFile = $this->getOutputFilename($this->_inputFile);
        $this->_mappingTemplate = $this->loadMappingTemplate($this->_inputFile);
    }

    // Generate output filename based on input file basename.
    private function getOutputFilename($inputFile)
    {
        $basename = pathinfo($inputFile, PATHINFO_FILENAME);
        return "{$basename}-normalized.csv";
    }

    // Load the mapping template from a JSON file based on the input filename.
    private function loadMappingTemplate($inputFile)
    {
        $basename = pathinfo($inputFile, PATHINFO_FILENAME);
        $mappingFile = __DIR__ . "/maps/{$basename}.json";

        if (!file_exists($mappingFile)) {
            die("Mapping template for {$basename} not found.");
        }

        // Load and decode the JSON mapping template
        $json = file_get_contents($mappingFile);
        return json_decode($json, true);
    }
    
    private function readCSV()
    {
        $rows = [];
        if (($handle = fopen($this->_inputFile, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }

    public function processFile()
    {

        if (($handle = fopen($this->_inputFile, 'r')) !== false) {
            // Open the output file for writing
            $outputHandle = fopen($this->_outputFile, 'w');

            // Write the header row to the output CSV.
            fputcsv($outputHandle, ProductModel::getCSVHeader());

            // Process rows one at a time to avoid memory issues
            $rowIndex = 0;

            // Loop through the input CSV file.
            while (($row = fgetcsv($handle, 10000, ',')) !== false) {
                // Skip the first row if it is a header row
                if (($this->_mappingTemplate['has_header_row'] == true) && $rowIndex == 0) {
                    $rowIndex++;
                    continue;
                }
                $rowIndex++;
                
                $this->_totalRows++;
                //echo "Processing row: {$rowIndex}\n";

                // Map the row to the ProductModel
                $product = $this->mapRowToProductModel($row, $rowIndex);

                // Write the processed row to the output CSV
                fputcsv($outputHandle, $product->toCSV());

                // Free up memory if needed
                unset($product);  // Ensures we don't accumulate objects in memory
            }

            // Close both the input and output files
            fclose($handle);
            fclose($outputHandle);
        }
        $this->_end_time = microtime(true);
        $execution_time = $this->_end_time - $this->_start_time;

        // Convert execution time to a readable format (seconds)
        echo "Script execution time: " . $execution_time . " seconds";
        print_r("\n");
        print_r("Total Rows: ");
        print_r($this->_totalRows);
        print_r("\n");
        print_r("Completed Rows: ");
        print_r($this->_completedRows);
        print_r("\n");
        print_r("Empty Rows: ");
        print_r($this->_emptyRows);
        print_r("\n");
        print_r("Incorrect Rows: ");
        print_r($this->_incorrectRows);
        print_r("\n");
        
    }


    private function saveToCSV($data)
    {
        $handle = fopen($this->_outputFile, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    private function mapRowToProductModel($row, $rowIndex)
    {
        $product = new ProductModel();

        // DEBUG: Output the raw row to make sure data is being read correctly
        //print_r($row); // This will help you see what data is actually being processed

        // Check if the row is empty.
        if (count($row) < 2) {
            $this->_emptyRows++;
            echo "Empty row encountered\n";
            return $product;
        }

        // Check if the row has the expected number of columns.
        if (count($row) != $this->_mappingTemplate['expected_row_count']) {
            $this->_incorrectRows++;
            echo "Row has incorrect number of columns\n";
            print_r("Row Index: ");
            print_r($rowIndex);
            print_r("\n");
            print_r("expected_row_count: ");
            print_r($this->_mappingTemplate['expected_row_count']);
            print_r("\n");
            print_r("Row Count: ");
            print_r(count($row));
            print_r("\n");
            print_r("Row: ");
            print_r($row); // Output the row to help with debugging.
            print_r("\n");
            echo "Press any key to continue...";

            // This will read a single character from the command line input
            fgetc(STDIN);
            
            return $product;
        }

        $product->vendor = isset($this->_mappingTemplate['vendor']) && $this->_mappingTemplate['vendor'] !== null
        ? $this->_mappingTemplate['vendor']
        : "";
        // Map ID (SKU)
        $product->sku = isset($this->_mappingTemplate['sku']) && $this->_mappingTemplate['sku'] !== null
        ? $row[$this->_mappingTemplate['sku']]
        : null;

        // Map UPC
        $product->upc = isset($this->_mappingTemplate['upc']) && $this->_mappingTemplate['upc'] !== null
        ? $row[$this->_mappingTemplate['upc']]
        : null;

        // Map Quantity
        $product->quantity = isset($this->_mappingTemplate['quantity']) && $this->_mappingTemplate['quantity'] !== null
        ? $row[$this->_mappingTemplate['quantity']]
        : 0;

        // Map Price (Dealer or other)
        $product->cost = isset($this->_mappingTemplate['cost']) && $this->_mappingTemplate['cost'] !== null
        ? $row[$this->_mappingTemplate['cost']]
        : 0;

        // Map MAP Price (minimum advertised price)
        $product->map = isset($this->_mappingTemplate['map']) && $this->_mappingTemplate['map'] !== null
        ? $row[$this->_mappingTemplate['map']]
        : 0;

        // Map MSRP
        $product->msrp = isset($this->_mappingTemplate['msrp']) && $this->_mappingTemplate['msrp'] !== null
        ? $row[$this->_mappingTemplate['msrp']]
        : 0;

        // Map Sale Price
        $product->sale_price = isset($this->_mappingTemplate['sale_price']) && $this->_mappingTemplate['sale_price'] !== null
        ? $row[$this->_mappingTemplate['sale_price']]
        : 0;

        // Map Manufacturer
        $product->manufacturer = isset($this->_mappingTemplate['manufacturer']) && $this->_mappingTemplate['manufacturer'] !== null
        ? $row[$this->_mappingTemplate['manufacturer']]
        : "Unknown";

        // Map Model
        $product->model = isset($this->_mappingTemplate['model']) && $this->_mappingTemplate['model'] !== null
        ? $row[$this->_mappingTemplate['model']]
        : "N/A";

        // Map Item Name
        $product->item_name = isset($this->_mappingTemplate['item_name']) && $this->_mappingTemplate['item_name'] !== null
        ? $row[$this->_mappingTemplate['item_name']]
        : "N/A";

        // Map Description
        $product->description = isset($this->_mappingTemplate['description']) && $this->_mappingTemplate['description'] !== null
        ? $row[$this->_mappingTemplate['description']]
        : "No description available";

        // Map Category
        $product->category = isset($this->_mappingTemplate['category']) && $this->_mappingTemplate['category'] !== null
        ? $row[$this->_mappingTemplate['category']]
        : "N/A";

        // Map Gun Type (optional)
        $product->gun_type = isset($this->_mappingTemplate['gun_type']) && $this->_mappingTemplate['gun_type'] !== null
        ? $row[$this->_mappingTemplate['gun_type']]
        : "N/A";

        // Map Caliber (optional)
        $product->caliber = isset($this->_mappingTemplate['caliber']) && $this->_mappingTemplate['caliber'] !== null
        ? $row[$this->_mappingTemplate['caliber']]
        : "N/A";

        // Map Action (optional)
        $product->action = isset($this->_mappingTemplate['action']) && $this->_mappingTemplate['action'] !== null
        ? $row[$this->_mappingTemplate['action']]
        : "N/A";

        // Map Capacity (optional)
        $product->capacity = isset($this->_mappingTemplate['capacity']) && $this->_mappingTemplate['capacity'] !== null
        ? $row[$this->_mappingTemplate['capacity']]
        : "N/A";

        // Map Finish (optional)
        $product->finish = isset($this->_mappingTemplate['finish']) && $this->_mappingTemplate['finish'] !== null
        ? $row[$this->_mappingTemplate['finish']]
        : "N/A";

        // Map Stock (optional)
        $product->stock = isset($this->_mappingTemplate['stock']) && $this->_mappingTemplate['stock'] !== null
        ? $row[$this->_mappingTemplate['stock']]
        : "N/A";

        // Map Sights (optional)
        $product->sights = isset($this->_mappingTemplate['sights']) && $this->_mappingTemplate['sights'] !== null
        ? $row[$this->_mappingTemplate['sights']]
        : "N/A";

        // Map Barrel Length (optional)
        $product->barrel_length = isset($this->_mappingTemplate['barrel_length']) && $this->_mappingTemplate['barrel_length'] !== null
        ? $row[$this->_mappingTemplate['barrel_length']]
        : "N/A";

        // Map Overall Length (optional)
        $product->overall_length = isset($this->_mappingTemplate['overall_length']) && $this->_mappingTemplate['overall_length'] !== null
        ? $row[$this->_mappingTemplate['overall_length']]
        : "N/A";

        // Map Drop Ship Flag
        $product->drop_ship_flag = isset($this->_mappingTemplate['drop_ship_flag']) && $this->_mappingTemplate['drop_ship_flag'] !== null
        ? $row[$this->_mappingTemplate['drop_ship_flag']]
        : "N/A";

        // Map Drop Ship Price (optional)
        $product->drop_ship_price = isset($this->_mappingTemplate['drop_ship_price']) && $this->_mappingTemplate['drop_ship_price'] !== null
        ? $row[$this->_mappingTemplate['drop_ship_price']]
        : 0;

        // Map Ship Weight (optional)
        $product->ship_weight = isset($this->_mappingTemplate['ship_weight']) && $this->_mappingTemplate['ship_weight'] !== null
        ? $row[$this->_mappingTemplate['ship_weight']]
        : 0;

        // Map Image Location (optional)
        $product->image_location = isset($this->_mappingTemplate['image_location']) && $this->_mappingTemplate['image_location'] !== null
        ? $row[$this->_mappingTemplate['image_location']]
        : "No image";

        // Map Allocated Item (optional)
        $product->allocated_item = isset($this->_mappingTemplate['allocated_item']) && $this->_mappingTemplate['allocated_item'] !== null
        ? $row[$this->_mappingTemplate['allocated_item']]
        : "N/A";
        $this->_completedRows++;
        return $product;
    }

}

$inputFile = __DIR__ . '/data/chattanoogashooting.csv'; // Input filename (can be provided dynamically)
//$inputFile = __DIR__ . '/data/davidsons_inventory.csv'; // Input filename (can be provided dynamically)
$processor = new Commonize_Import_Data($inputFile, null);
$processor->processFile();


class ProductModel
{
    public $vendor;
    public $sku;
    public $upc;
    public $quantity;
    public $cost;
    public $map;
    public $msrp;
    public $sale_price;
    public $sale_start_date;
    public $sale_end_date;
    public $manufacturer;
    public $model;
    public $item_name;
    public $description;
    public $category;
    public $gun_type;
    public $caliber;
    public $action;
    public $capacity;
    public $finish;
    public $stock;
    public $sights;
    public $barrel_length;
    public $overall_length;
    public $drop_ship_flag;
    public $drop_ship_price;
    public $ship_weight;
    public $image_location;
    public $length;
    public $width;
    public $height;
    public $available_drop_ship_delivery_options;
    public bool $allocated_item;

    /**
     * ImportModel constructor.
     */
    public function __construct()
    {
        return $this;
    }
    
    /**
     * Export the object to CSV
     *
     * @return array
     */
    public function toCSV(): array
    {
        //return implode(',', get_object_vars($this));
        return get_object_vars($this);
    }

    /**
     * Create a header row for the CSV file
     * 
     * @return array
     */
    public static function getCSVHeader(): array
    {
        $object = new self();
        return array_keys(get_object_vars($object));
    }


    
}
