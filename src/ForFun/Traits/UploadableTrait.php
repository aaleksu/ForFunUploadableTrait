<?php

namespace ForFun\Traits;

trait UploadableTrait
{
    private $file;

    private $tempFile;

    public abstract function getUploadDir();

    public abstract function uploadableProperties();

    public function __get($name)
    {
        return $this->__call('get' , ucfirst($name));
    }

    public function __set($name, $arg)
    {
        return $this->__call('set' . ucfirst($name), [$arg]);
    }

    public function __call($name, $args)
    {
        preg_match('/(get|set)/i', $name, $matches);
        if(empty($matches)) {
            return;
        }

        $methodType = $matches[0];

        $prop = str_replace($matches[0], '', $name);
        $prop = lcfirst($prop);
        foreach($this->uploadableProperties() as $uploadableProp) {
            if($prop == $uploadableProp || $prop == $uploadableProp . 'File') {
                if($methodType == 'set' && !empty($args)) {
                    $this->{$prop} = $args[0];

                    return $this;
                }

                if($methodType == 'get') {
                    if(preg_match('/file/i', $name)) {
                        return $this->{$prop . 'File'};
                    }
                    return $this->{$prop};
                }
            }
        }
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function preUpload()
    {
        foreach($this->uploadableProperties() as $uploadableProp) {
            if (null !== $this->{$uploadableProp . 'File'}) {
                // do whatever you want to generate a unique name
                $filename = sha1(uniqid(mt_rand(), true));
                $this->{$uploadableProp} = $filename.'.'.$this->{$uploadableProp . 'File'}->guessExtension();
            }
        }
    }

    /**
     * @ORM\PostPersist()
     * @ORM\PostUpdate()
     */
    public function upload()
    {
        foreach ($this->uploadableProperties() as $uploadableProp) {
            if (null === $this->{$uploadableProp . 'File'}) {
                return;
            }

            // if there is an error when moving the file, an exception will
            // be automatically thrown by move(). This will properly prevent
            // the entity from being persisted to the database on error
            $this->{$uploadableProp . 'File'}->move($this->getUploadRootDir(), $this->{$uploadableProp});

            // check if we have an old image
            if (isset($this->{$uploadableProp . 'Temp'})) {
                // delete the old image
                unlink($this->getUploadRootDir() . '/' . $this->{$uploadableProp . 'Temp'});
                // clear the temp image path
                $this->{$uploadableProp . 'Temp'} = null;
            }
            $this->{$uploadableProp . 'Temp'} = null;
        }
    }

    /**
     * @ORM\PostRemove()
     */
    public function removeUpload()
    {
        foreach ($this->uploadableProperties() as $uploadableProp) {
            $file = $this->getAbsolutePath($uploadableProp);
            if ($file) {
                unlink($file);
            }
        }
    }


    public function getAbsolutePath($prop)
    {
        foreach ($this->uploadableProperties() as $uploadableProp) {
            if($prop == $uploadableProp) {
                return null === $this->{$uploadableProp}
                    ? null
                    : $this->getUploadRootDir() . '/' . $this->{$uploadableProp};
            }
        }
    }

    public function getWebPath($prop)
    {
        foreach ($this->uploadableProperties() as $uploadableProp) {
            if($prop == $uploadableProp) {
                return null === $this->{$uploadableProp}
                    ? null
                    : $this->getUploadDir() . '/' . $this->{$uploadableProp};
            }
        }
    }

    protected function getUploadRootDir()
    {
        return __DIR__ . '/../../../../../web/' . $this->getUploadDir();
    }
}
