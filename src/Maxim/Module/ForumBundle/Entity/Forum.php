<?php

namespace Maxim\Module\ForumBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
/**
 * Maxim\CMSBundle\Entity\Forum
 *
 * @ORM\Table(name="mcsf_forum")
 * @ORM\Entity(repositoryClass="ForumRepository")
 */
class Forum {

    protected $id;

    protected $category;

    protected $title;

    protected $description;

    protected $createdBy;

    protected $createdOn;

    protected $updatedBy;

    protected $updatedOn;

    protected $threads;

    protected $showOnHome = false;

    protected $sort = 0;

    public function __construct()
    {
        $this->setCreatedOn(new \DateTime("now"));
    }

    /**
     * @param \Maxim\Module\ForumBundle\Entity\Category $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * @return \Maxim\Module\ForumBundle\Entity\Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param \Maxim\Module\ForumBundle\Entity\User $createdBy
     */
    public function setCreatedBy($createdBy)
    {
        $this->createdBy = $createdBy;
    }

    /**
     * @return \Maxim\Module\ForumBundle\Entity\User
     */
    public function getCreatedBy()
    {
        return $this->createdBy;
    }

    /**
     * @param \Maxim\Module\ForumBundle\Entity\datetime $createdOn
     */
    public function setCreatedOn($createdOn)
    {
        $this->createdOn = $createdOn;
    }

    /**
     * @return \Maxim\Module\ForumBundle\Entity\datetime
     */
    public function getCreatedOn()
    {
        return $this->createdOn;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $sort
     */
    public function setSort($sort)
    {
        $this->sort = $sort;
    }

    /**
     * @return string
     */
    public function getSort()
    {
        return $this->sort;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param \Maxim\Module\ForumBundle\Entity\User $updatedBy
     */
    public function setUpdatedBy($updatedBy)
    {
        $this->updatedBy = $updatedBy;
    }

    /**
     * @return \Maxim\Module\ForumBundle\Entity\User
     */
    public function getUpdatedBy()
    {
        return $this->updatedBy;
    }

    /**
     * @param \Maxim\Module\ForumBundle\Entity\datetime $updatedOn
     */
    public function setUpdatedOn($updatedOn)
    {
        $this->updatedOn = $updatedOn;
    }

    /**
     * @return \Maxim\Module\ForumBundle\Entity\datetime
     */
    public function getUpdatedOn()
    {
        return $this->updatedOn;
    }

    /**
     * @param \Maxim\Module\ForumBundle\Entity\Thread $threads
     */
    public function setThreads($threads)
    {
        $this->threads = $threads;
    }

    /**
     * @return \Maxim\Module\ForumBundle\Entity\Thread
     */
    public function getThreads()
    {
        return $this->threads;
    }

    public function __toString() {
        return $this->category . " > " . $this->category . " > " . $this->title;
    }

    public function getLatestThread() {

        $latest = null;

        foreach($this->threads as $thread)
        {
            if($latest == null) {
                $latest = $thread;
            } else {
                if($latest->getCreatedOn() < $thread->getCreatedOn())
                {
                    $latest = $thread;
                }
            }
        }

        return $latest;
    }

    public function findAmountPosts()
    {
        $amount = 0;
        foreach($this->threads as $thread)
        {
            $amount += count($thread->getPosts());
        }
        return $amount;
    }

    /**
     * @param boolean $showOnHome
     */
    public function setShowOnHome($showOnHome)
    {
        $this->showOnHome = $showOnHome;
    }

    /**
     * @return boolean
     */
    public function getShowOnHome()
    {
        return $this->showOnHome;
    }

}