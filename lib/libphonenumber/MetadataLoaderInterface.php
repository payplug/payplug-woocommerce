<?php

namespace libphonenumber;

interface MetadataLoaderInterface
{
    /**
     * @param string $metadataFileName file name (including path) of metadata to load
     *
     * @return mixed
     */
    public function loadMetadata($metadataFileName);
}
