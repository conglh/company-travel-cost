<?php

class Travel
{
    protected $id;
    protected $employeeName;
    protected $departure;
    protected $destination;
    protected $price;
    protected $companyId;
    protected $createdAt;

    public function __construct($id, $employeeName, $departure, $destination, $price, $companyId, $createdAt) {
        $this->id = $id;
        $this->employeeName = $employeeName;
        $this->departure = $departure;
        $this->destination = $destination;
        $this->price = $price;
        $this->companyId = $companyId;
        $this->createdAt = $createdAt;
    }

    public function getPrice() {
        return (float)$this->price;
    }
}

class Company
{
    protected $id;
    protected $name;
    protected $createdAt;
    protected $parentId;
    protected $childCompanies;
    protected $travels;

    public function __construct($id, $name, $createdAt, $parentId) {
        $this->id = $id;
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->parentId = $parentId;
        $this->childCompanies = [];
        $this->travels = [];
    }

    public function getId() {
        return $this->id;
    }

    public function getParentId() {
        return $this->parentId;
    }

    /**
     * @param Travel[] $travels
     * @return void
     */
    public function addTravels(array $travels) {
        $this->travels = $travels;
    }

    public function getTotalTravelCost() {
        $totalCost = 0;
        foreach ($this->travels as $travel) {
            if ($travel instanceof Travel) {
                $totalCost += $travel->getPrice();
            }
        }

        return (float)$totalCost;
    }

    /**
     * @param Company[] $companies
     * @return void
     */
    public function setChildCompanies(array $companies) {
        $this->childCompanies = $companies;
    }

    public function getTravelCostData() {
        $childCompanyData = [];
        $totalTravelCost = $this->getTotalTravelCost();
        
        foreach ($this->childCompanies as $company) {
            $childCompanyData[] = $company->getTravelCostData();
        }

        // sum up the total travel cost of child companies
        array_walk($childCompanyData, function($data) use (&$totalTravelCost) {
            $totalTravelCost += $data['cost'];
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'cost' => $totalTravelCost,
            'children' => $childCompanyData
        ];
    }
}

class TravelHelpers
{
    const TRAVEL_DATA_ENDPOINT = 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/travels';

    /**
     * @return array
     */
    public static function getTravelsIndexedByCompanyId() {
        $travels = [];
        $travelData = (new DataCrawler())->get(self::TRAVEL_DATA_ENDPOINT);

        foreach ($travelData as $travel) {
            $companyId = $travel['companyId'];
            $travels[$companyId][] = new Travel(
                $travel['id'], $travel['employeeName'], $travel['departure'],
                $travel['destination'], $travel['price'], $companyId, $travel['createdAt']
            );
        }

        return $travels;
    }
}

class CompanyHelpers
{

    const COMPANY_DATA_ENDPOINT = 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/companies';

    /**
     * @param array $companies
     * @param $parentId
     * @return array
     */
    public static function buildCompanyHierarchical(array $companies, $parentId = 0) {
        $companyHierarchical = [];

        foreach ($companies as $company) {
            if (!$company instanceof Company) {
                continue;
            }
            if ($company->getParentId() == "{$parentId}") {
                $childCompanies = self::buildCompanyHierarchical($companies, $company->getId());
                if ($childCompanies) {
                    $company->setChildCompanies($childCompanies);
                }

                $companyHierarchical[] = $company;
            }
        }

        return $companyHierarchical;
    }

    /**
     * @return array
     */
    public static function getCompanyTravelCost() {
        $companies = self::getCompaniesWithTravels();
        $rebuildCompanyStructure = self::buildCompanyHierarchical($companies);

        $companyTravelCost = [];
        foreach ($rebuildCompanyStructure as $company) {
            $companyTravelCost[] = $company->getTravelCostData();
        }

        return $companyTravelCost;
    }

    /**
     * @return array
     */
    public static function getCompaniesWithTravels() {
        $companies = [];
        $travels = TravelHelpers::getTravelsIndexedByCompanyId();
        $companyData = (new DataCrawler())->get(self::COMPANY_DATA_ENDPOINT);
        foreach ($companyData as $company) {
            $companyId = $company['id'];
            $company = new Company($companyId, $company['name'], $company['createdAt'], $company['parentId']);
            $company->addTravels($travels[$companyId] ? : []);
            $companies[$companyId] = $company;
        }

        return $companies;
    }
}

class DataCrawler
{
    /**
     * @param $url
     * @return array|mixed
     */
    public function get($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ? : [];
    }
}

class TestScript
{
    public function execute()
    {
        $start = microtime(true);

        $result = CompanyHelpers::getCompanyTravelCost();
        echo json_encode($result);
        echo PHP_EOL;

        echo 'Total time: '.  (microtime(true) - $start);
    }

}

(new TestScript())->execute();