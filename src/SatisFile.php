<?php
/**
 * This file is part of holisatis.
 *
 * (c) Gil <gillesodret@users.noreply.github.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace holisticagency\satis\utilities;

use Composer\Json\JsonFile;
use Composer\Repository\RepositoryInterface;

/**
 * Satis configuration file utilities.
 *
 * @author Gil <gillesodret@users.noreply.github.com>
 */
class SatisFile
{
    /**
     * An arbitrary name.
     *
     * @var string
     */
    private $name = 'default name';

    /**
     * Homepage of the static Composer repository.
     *
     * @var string
     */
    private $homepage;

    /**
     * Indexed Repositories by md5 hash.
     *
     * @var array
     */
    private $repositories = array();

    /**
     * Configuration array.
     *
     * @var array
     */
    private $satisConfig;

    /**
     * Constructor.
     *
     * @param string       $homepage       Homepage
     * @param array|string $existingConfig Array of an existing configuration
     */
    public function __construct($homepage, $existingConfig = null)
    {
        $this->homepage = rtrim($homepage, '/');
        if (is_string($existingConfig) && $tmpConfig = JsonFile::parseJson($existingConfig)) {
            $existingConfig = $tmpConfig;
        }
        $this->satisConfig = $existingConfig ?: $this->getDefaultConfig();
        foreach ($this->satisConfig['repositories'] as $index => $satisRepository) {
            $this->repositories[$index] = md5(implode($satisRepository));
        }
    }

    /**
     * Gives a default Configuration when creating a repository.
     *
     * @return array default Configuration
     */
    private function getDefaultConfig()
    {
        return array(
            'name'  => $this->name,
            'homepage' => $this->homepage,
            'repositories' => array(),
            'require-all' => true,
            'archive' => array(
                'directory' => 'dist',
                'format' => 'zip',
            ),
            'output-html' => false,
        );
    }

    /**
     * Check the type of a repository.
     *
     * @param RepositoryInterface $repository a repository to set or unset in the configuration
     *
     * @throws \Exception If $repository is of an unsupported class
     *
     * @return array Combination of a type and an url
     */
    private function setRepositoryHelper(RepositoryInterface $repository)
    {
        $satisRepository = array();
        if ($repository instanceof \Composer\Repository\ComposerRepository) {
            $repository = new SatisComposerRepository($repository);
            $satisRepository = array('type' => 'composer', 'url' => $repository->getUrl());
        } elseif ($repository instanceof \Composer\Repository\VcsRepository) {
            $repo = $repository->getRepoConfig();
            $satisRepository = array('type' => $repo['type'], 'url' => $repo['url']);
        } elseif ($repository instanceof \Composer\Repository\ArtifactRepository) {
            $repository = new SatisArtifactRepository($repository);
            $satisRepository = array('type' => 'artifact', 'url' => $repository->getLookup());
        } else {
            throw new \Exception("Error Processing Request", 1);
        }

        return $satisRepository;
    }

    /**
     * Set a Repository in the configuration.
     *
     * @param RepositoryInterface $repository The repository to set
     *
     * @return SatisFile this SatisFile Instance
     */
    public function setRepository(RepositoryInterface $repository)
    {
        $satisRepository = $this->setRepositoryHelper($repository);
        //Check url
        $changeType = false;
        foreach ($this->satisConfig['repositories'] as $index => $configRepository) {
            if ($satisRepository['url'] === $configRepository['url']) {
                //Change type of repository
                $changeType = $index;
            }
        }
        $hashedSatisRepo = md5(implode($satisRepository));
        if (is_numeric($changeType)) {
            //Change type of repository
            $this->satisConfig['repositories'][$index]['type'] = $satisRepository['type'];
            $this->repositories[$index] = $hashedSatisRepo;
        } elseif (!in_array($hashedSatisRepo, $this->repositories)) {
            //Add a repository
            $index = count($this->satisConfig['repositories']);
            $this->satisConfig['repositories'][] = $satisRepository;
            $this->repositories[$index] = $hashedSatisRepo;
        }

        return $this;
    }

    /**
     * Sets the name of the repository.
     * 
     * @param string $name the new name of the repository
     *
     * @return SatisFile this SatisFile Instance
     */
    public function setName($name = 'default name')
    {
        $this->name = $name;
        $this->satisConfig['name'] = $name;

        return $this;
    }

    /**
     * Unset a Repository out of the configuration.
     *
     * @param RepositoryInterface $repository The repository to set
     *
     * @return SatisFile this SatisFile Instance
     */
    public function unsetRepository(RepositoryInterface $repository)
    {
        $satisRepository = $this->setRepositoryHelper($repository);
        $hashedSatisRepo = md5(implode($satisRepository));
        $index = array_search($hashedSatisRepo, $this->repositories);
        if (is_numeric($index)) {
            //Remove a repository
            array_splice($this->satisConfig['repositories'], $index, 1);
            array_splice($this->repositories, $index, 1);
        }

        return $this;
    }

    /**
     * Configuration as an array.
     *
     * @return array Configuration
     */
    public function asArray()
    {
        return $this->satisConfig;
    }

    /**
     * Configuration as a Json string.
     *
     * @return string Configuration
     */
    public function json()
    {
        return JsonFile::encode($this->satisConfig);
    }
}
