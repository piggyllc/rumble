<?php 
namespace en0ma\Drone;

abstract class Migration
{
    /**
        Dynamodb table params placeholder.
    **/
    private $tableParams = [];

    /**
        AWS DynamodbClient.
    **/
    private $dynamoDBCLient;

    /**
        Set dynamodbClinet.
    **/
    public function __construct($dynamoDBCLient)
    {
        $this->dynamoDBCLient = $dynamoDBCLient;
    }

    /**
        Set the dynamodb table name.
    **/
    protected function table(String $name)
    {
        $this->tableParams['TableName'] = $name;
        return $this;
    }

    /**
        Add an attribute to the table.
    **/
    protected function addAttribute(string $name, string $dataType)
    {
        $this->setAttributeDefinitions();
        array_push($this->tableParams['AttributeDefinitions'], ['AttributeName' => $name,'AttributeType' => $dataType]);
        return $this;
    }

    /**
        Add a simple primary key to the table.
    **/
    protected function addHash(string $attributeName)
    {
        $this->setKeySchema();
        array_push($this->tableParams['KeySchema'], ['AttributeName' => $attributeName, 'KeyType' => 'HASH']);
        return $this;
    }

    /**
        Add a composite (range) key to the table.
    **/
    protected function addRange(string $attributeName)
    {
        $this->setKeySchema();
        array_push($this->tableParams['KeySchema'],  ['AttributeName' => $attributeName, 'KeyType' => 'HASH']);
        return $this;
    }   

    /**
        Add a write capacity unit value to the table.
    **/
    protected function setWCU(int $unit)
    {
        $this->setProvisionedThroughput();
        $this->tableParams['ProvisionedThroughput']['WriteCapacityUnits'] = $unit;
        return $this;
    }

    /**
        Add a read capacity unit value to the table.
    **/
    protected function setRCU(int $unit)
    {
        $this->setProvisionedThroughput();
        $this->tableParams['ProvisionedThroughput']['ReadCapacityUnits'] = $unit;
        return $this;
    }

     /**
        Set the AttributeDefinition to an empty array. 
        This will make setting the attributes easier.
    **/
    private function setAttributeDefinitions()
    {
        if (!isset($this->tableParams['AttributeDefinitions'])) $this->tableParams['AttributeDefinitions'] = [];
    }

    /**
        Set the Keyschema to an empty array. 
        This will make setting the primary key(s) easier.
    **/
    private function setKeySchema()
    {
        if (!isset($this->tableParams['KeySchema'])) $this->tableParams['KeySchema'] = [];
    }

    /**
        Verify that the TableName param is set.
        This is a mandatory param, just like the hash key.
    **/
    private function isTableNameSet()
    {
        if (!isset($this->tableParams['TableName']))
            throw new \Exception('Error: DynamoDB requires table name to be specified.');
    }

    /**
        Very that a valid primary key was added with a corresponding valid 
        attribute name.
    **/
    private function isHashSet()
    {
        $hashKeysFound = [];
        foreach ($this->tableParams['KeySchema'] as $key) {
            if ('HASH' == $key['KeyType'])
                $hashKeysFound[] = $key;
        }

        if (count($hashKeysFound) != 1)
            throw new \Exception('Error: DynamoDB requires at least a simple primary key.');
        
        $attributesFound = [];
        foreach ($this->tableParams['AttributeDefinitions'] as $definition) {
            if ($hashKeysFound[0]['AttributeName'] == $definition['AttributeName'])
                $attributesFound[] = $definition;
        }

        if (count($attributesFound) != 1)
           throw new \Exception('Error: DynamoDB requires a matching attribute for a hash(primary) key.');
    }

    /**
        Set the setProvisionedThroughput param to an empty array.
        This will make setting up ReadCapacityUnit and WriteCapacityUnit up easier.
    **/
    private function setProvisionedThroughput()
    {
        if(!isset($this->tableParams['ProvisionedThroughput'])) $this->tableParams['ProvisionedThroughput'] = [];
    }

    /**
        Show completion message, when migration is successful.
    **/
    private function displayCompletionMessage()
    {
        $className = get_class($this);
        echo "{$className} Migrated successfully".PHP_EOL;
    }

    /**
        Check if table exist in dynamodb.
    **/
    private function tableExist($table)
    {
        $result = $this->dynamoDBCLient->listTables();
        return in_array($table, $result['TableNames']);
    }

    /** 
        Create a new Dynamodb Table.
    **/
    protected function create()
    {
        $this->isTableNameSet();
        $this->isHashSet();

        if (!$this->tableExist($this->tableParams['TableName'])) {
            $this->dynamoDBCLient->createTable($this->tableParams);
            $this->dynamoDBCLient->waitUntil('TableExists', $this->tableParams);
        }
        return $this->displayCompletionMessage();
    }

    /**
        Delete Dynamodb Table.
    **/
    protected function delete()
    {
        $this->isTableNameSet();
        $this->dynamoDBCLient->deleteTable($this->tableParams);
        $this->dynamoDBCLient->waitUntil('TableNoExists', $this->tableParams);
        return $this->displayCompletionMessage();
    }

    /**
        Update dynamodb Table.
    **/
    protected function update()
    {
        $this->isTableNameSet();
        $this->dynamoDBCLient->updateTable($this->tableParams);
        $this->dynamoDBCLient->waitUntil('TableExists', $this->tableParams);
        return $this->displayCompletionMessage();
    }

    /**
        Force migration files to define the up method.
    **/
    protected abstract function up();
}
