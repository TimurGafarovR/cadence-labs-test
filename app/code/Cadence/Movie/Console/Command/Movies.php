<?php
namespace Cadence\Movie\Console\Command;

use Cadence\Movie\Helper\Config;
use Cadence\Movie\Service\MovieDbService;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Io\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Movies extends Command
{
    private State $state;
    private ProductFactory $productFactory;
    private StockItemInterface $stockItem;
    private StockItemRepositoryInterface $stockItemRepository;
    private CategoryLinkManagementInterface $categoryLinkManagement;
    private ProductRepositoryInterface $productRepository;
    private MovieDbService $movieDbService;
    private Config $config;
    private File $file;
    private DirectoryList $directoryList;

    public function __construct(
        ProductFactory $productFactory,
        State $state,
        StockItemInterface $stockItem,
        StockItemRepositoryInterface $stockItemRepository,
        CategoryLinkManagementInterface $categoryLinkManagement,
        ProductRepositoryInterface $productRepository,
        MovieDbService $movieDbService,
        Config $config,
        File $file,
        DirectoryList $directoryList
    ) {
        parent::__construct('moviedb:import');

        $this->state = $state;
        $this->productFactory = $productFactory;
        $this->stockItem = $stockItem;
        $this->stockItemRepository = $stockItemRepository;
        $this->categoryLinkManagement = $categoryLinkManagement;
        $this->productRepository = $productRepository;
        $this->movieDbService = $movieDbService;
        $this->config = $config;
        $this->file = $file;
        $this->directoryList = $directoryList;
    }

    protected function configure(): void
    {
        $this->setDescription('This is my first console command.');

        parent::configure();
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws GuzzleException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $exitCode = 0;

        $popularMovies = $this->movieDbService->getPopular();

        foreach ($popularMovies['results'] as $movie) {
            $product = $this->productFactory->create();

            // Setting values based on initial request
            $product->setSku($movie['id']);
            $product->setName($movie['title']);
            $product->setDescription($movie['overview']);

            // Getting current movie details and setting them into product
            $movieDetails = $this->movieDbService->getDetails($movie['id']);
            $product->setGenre($this->parseGenres($movieDetails));
            $product->setReleaseDate($this->parseYear($movieDetails));
            $product->setVoteAverage($movieDetails['vote_average']);

            // Getting current movie credits and setting them into product
            $movieCredits = $this->movieDbService->getCredits($movie['id']);
            $product->setProducer($this->parseCrewType($movieCredits, 'crew', 'Producer'));
            $product->setDirector($this->parseCrewType($movieCredits, 'crew', 'Director'));
            $product->setActors($this->parseCrewType($movieCredits, 'cast'));

            // Setting default required values
            $product->setStatus(1);
            $product->setVisibility(4);
            $product->setPrice(5.99);
            $product->setAttributeSetId(Config::MOVIE_ATTRIBUTE_SET_ID);
            $product->setWebsiteIds([1]);
            $product->setStockData([
                'manage_stock' => 0,
                'is_in_stock' => 1,
                'qty' => 100
            ]);

            // Download and attach images
            $this->attachImages($product, $movie['id']);

            $this->productRepository->save($product);
            $this->categoryLinkManagement->assignProductToCategories($movie['id'], [Config::MOVIE_CATEGORY_ID]);
        }

        return $exitCode;
    }

    /**
     * Parses out genres in comma separated format from movie details array
     * @param $movieDetails
     * @return string
     */
    protected function parseGenres($movieDetails): string
    {
        $genres = [];
        foreach ($movieDetails['genres'] as $genre) {
            $genres[] = $genre['name'];
        }
        return implode(', ', $genres);
    }

    /**
     * Parses out year of movie release from movie details
     * @param $movieDetails
     * @return string
     */
    protected function parseYear($movieDetails): string
    {
        $arYear = explode('-', $movieDetails['release_date']);
        return $arYear[0];
    }

    /**
     * Parses out all crew members with specific title supplied as a second argument
     * @param $movieCredits
     * @param $memberType
     * @param string $title
     * @return string
     */
    protected function parseCrewType($movieCredits, $memberType, string $title = ''): string
    {
        $workers = [];
        foreach ($movieCredits[$memberType] as $crewMember) {
            if (!empty($title) && $memberType === 'crew' && $crewMember['job'] === $title) {
                $workers[] = $crewMember['name'];
            }

            if ($memberType === 'cast') {
                $workers[] = $crewMember['name'];
            }
        }
        return implode(', ', $workers);
    }

    /**
     * Get images from API and attach them to the product
     * @throws FileSystemException
     * @throws LocalizedException
     * @throws InvalidArgumentException
     */
    protected function attachImages($product, $sku)
    {
        $tmpDir = $this->getMediaDirTmpDir();
        $this->file->checkAndCreateFolder($tmpDir);

        $images = $this->movieDbService->getImages($sku);
        foreach ($images['backdrops'] as $key => $image) {
            if ($key > 4) {
                break;
            }
            $imgUrl = $this->movieDbService->getImageBaseUrl() . 'original' . $image['file_path'];
            $newFileName = $tmpDir . baseName($imgUrl);
            $result = $this->file->read($imgUrl, $newFileName);
            if ($result) {
                $product->addImageToMediaGallery($newFileName, ['image', 'small_image', 'thumbnail'], true, false);
            }
        }
    }

    /**
     * Get directory path to the tmp folder
     * @throws FileSystemException
     */
    protected function getMediaDirTmpDir(): string
    {
        return $this->directoryList->getPath(DirectoryList::MEDIA) . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
    }
}
