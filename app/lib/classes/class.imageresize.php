<?php

/* --------------------------------------------------------------------

  Chevereto
  http://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
			<inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.
  
  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  http://chevereto.com/license

  --------------------------------------------------------------------- */

namespace CHV;
use G, Exception;

class Imageresize {

	// filename => name.ext
	// file => /full/path/to/name.ext
	// name => name
	
	public $resized;
	
	public function setSource($source) {
		$this->source = $source;
	}
	
	public function setDestination($destination) {
		$this->destination = $destination;
	}
	
	public function setFilename($name) {
		$this->filename = $name;
	}
	
	// Set options
	public function setOptions($options) {
		$this->options = $options;
	}
	
	// Set individual option
	public function setOption($key, $value) {
		$this->options[$key] = $value;
	}
	
	// Default options
	public function getDefaultOptions() {
		return array(
			'max_size'		=> G\get_bytes('2 MB'),
			'filenaming'	=> 'original'
		);
	}
	
	public function set_width($width) {
		$this->width = intval($width);
	}
	
	public function set_height($height) {
		$this->height = intval($height);
	}	
	
	public function set_fixed() {
		$this->fixed = true;
	}

	/**
	 * Do the thing
	 * @Exception 4xx
	 */
	public function exec() {
	
		$this->validateInput(); // Exception 1xx
		
		// Save the source filename
		$source_filename = G\get_filename_without_extension($this->source);
		
		// Set file extension
		$this->file_extension = $this->source_image_fileinfo['extension'];
		
		// Workaround the $filename
		if(!$this->filename) {
			$this->filename = $source_filename;
		}
		
		// Fix file extension
		if(G\get_file_extension($this->filename) == $this->resized_file_extension) {
			$this->filename = G\get_filename_without_extension($this->filename);
		}
		
		// Fix the destination path
		$this->destination = G\add_ending_slash($this->destination);
		
		// Set $resized_file
		$this->resized_file = $this->destination . $this->filename . '.' . $this->file_extension;

		/*
		if($this->source == $this->resized_file) {
			$this->resized_file = $this->destination . $this->filename . '.' . $this->file_extension;
		} else {
			$this->resized_file = G\name_unique_file($this->destination, $this->options['filenaming'] ? $this->options['filenaming'] : $this->getDefaultOptions()['filenaming'], $this->filename . '.' . $this->file_extension);
		}
		*/
		
		// Do the resize process
		$this->resize_image();
		
		$this->resized = [
			'file'		=> $this->resized_file,
			'filename'	=> G\get_filename($this->resized_file),
			'name'		=> G\get_filename_without_extension($this->resized_file),
			'fileinfo'	=> G\get_image_fileinfo($this->resized_file)
		];

	}
	
	// @Exception 1xx
	protected function validateInput() {
		
		$check_missing = ['source'];
		missing_values_to_exception($this, 'CHV\ImageresizeException', $check_missing, 100);
		
		if(!$this->width and !$this->height) {
			throw new ImageresizeException('Missing ' . '$width and/or ' . '$height', 102);
		}
		
		if(!$this->destination) {
			$this->destination = G\add_ending_slash(dirname($this->source));
		}

		// Validate $source file
		if(!file_exists($this->source)) {
			throw new ImageresizeException("Source file doesn't exists", 110);
		}
		
		// $source file looks like an image?
		$this->source_image_fileinfo = G\get_image_fileinfo($this->source);
		if(!$this->source_image_fileinfo) {
			throw new ImageresizeException("Can't get source image info", 111);
		}
		
		// Validate $destination
		if(!is_dir($this->destination)) {
			
			// Try to create the missing directory
			$old_umask = umask(0);
			$make_destination = @mkdir($this->destination, 0755, true);
			umask($old_umask);
			
			if(!$make_destination) {
				throw new ImageresizeException('$destination ' . $this->destination . ' is not a dir', 120);
			}
			
		}
		
		// Can read $destination dir? -> note: We only need to write and read the target file, no this dir.
		/*
		if(!is_readable($this->destination)) {
			throw new ImageresizeException("Can't read target destination dir " . $this->destination, 121);
		}
		*/
		
		// Can write $destination dir?
		if(!is_writable ($this->destination)) {
			throw new ImageresizeException("Can't write target destination dir " . $this->destination, 122);
		}
		
		// Validate width and height
		if($this->width and !is_int($this->width)) {
			throw new ImageresizeException('Expecting integer value in $width, ' . gettype($this->width) . ' given', 130);
		}
		
		if($this->height and !is_int($this->height)) {
			throw new ImageresizeException('Expecting integer value in $height, ' . gettype($this->width) . ' given', 131);
		}
		
	}
	
	// @Exception 2xx
	protected function resize_image() {
				
		// Fix the $width and $height vars
		if($this->width and $this->height) {
			$this->set_fixed();
		} else {
			if($this->fixed) {
				if($this->width) {
					$this->height = $this->width;
				} else {
					$this->width = $this->height;
				}
			} else {
				if($this->width) {
					$this->height = intval(round($this->width / $this->source_image_fileinfo['ratio']));
				} else {
					$this->width = intval(round($this->height * $this->source_image_fileinfo['ratio']));
				}
			}
		}

		$imageSX = $this->source_image_fileinfo['width'];
		$imageSY = $this->source_image_fileinfo['height'];
		
		// Do we actually need to resize?
		if($this->width == $imageSX and $this->height == $imageSY and !$this->options['forced']) {
			@copy($this->source, $this->resized_file);
			return;
		}
		
		switch($this->file_extension) {
			case 'gif':
				$src = imagecreatefromgif($this->source);
			break;
			case 'png':
				$src = imagecreatefrompng($this->source);
			break;
			case 'jpg':
				$src = imagecreatefromjpeg($this->source);
			break;
		}
		
		// Invalid SRC
		if(!$src) {
			throw new ImageresizeException("Can't create image from source", 210);
		}
		
		if($this->fixed) {
			$source_ratio = $this->source_image_fileinfo['ratio'];
			$destination_ratio = $this->width / $this->height;
			
			// Ratio thing
			if ($destination_ratio > $source_ratio) {
			   $ratio_height = round($this->width / $source_ratio);
			   $ratio_width = $this->width;
			} else {
			   $ratio_width = round($this->height * $source_ratio);
			   $ratio_height = $this->height;
			}
				
			$target = imagecreatetruecolor($ratio_width, $ratio_height);
			
			$x_center = $ratio_width / 2;
			$y_center = $ratio_height / 2;
			
		} else {
			$target = imagecreatetruecolor($this->width, $this->height);
		}

		// Copies SRC to TARGET
		// Allocate SRC transparency
		if(preg_match('/^(png|gif)$/', $this->file_extension)) {
			G\image_allocate_transparency($src, $this->file_extension);
			G\image_copy_transparency($src, $target);
		}
			
		if($this->fixed) {
			
			imagecopyresampled($target, $src, 0, 0, 0, 0, $ratio_width, $ratio_height, $imageSX, $imageSY);
			$process = imagecreatetruecolor($this->width, $this->height);
			
			// Re-allocate the transparency
			if($this->file_extension == 'gif') {
				G\image_copy_transparency($process, $target);
				G\image_copy_transparency($target, $process);
			}
			if($this->file_extension == 'png') {
				G\image_allocate_transparency($process, $this->file_extension);
				G\image_allocate_transparency($target, $this->file_extension);
			} 
			imagecopyresampled($process, $target, 0, 0, ($x_center - ($this->width / 2)), ($y_center - ($this->height / 2)), $this->width, $this->height, $this->width, $this->height);
			imagedestroy($target);
			
		} else {
			//if($this->file_extension == "gif") G\image_copy_transparency($target, $process);
			if($this->file_extension == 'png') G\image_allocate_transparency($target, $this->file_extension);
			imagecopyresampled($target, $src, 0, 0, 0, 0, $this->width, $this->height, $imageSX, $imageSY);
			$process = $target;
		}
		
		// Sharpen the image just for JPG
		// This needs a little more debug since GD 2.1.1 (dev)
		/*
		if($this->file_extension == 'jpg') {
			$matrix = array(array(-1, -1, -1), array(-1, 32, -1), array(-1, -1, -1));
			$divisor = array_sum(array_map('array_sum', $matrix));
			imageconvolution($process, $matrix, $divisor, 0);
		}
		*/
		
		// Creates the image
		switch($this->file_extension) {
			case 'gif':
				$output_image = imagegif($process, $this->resized_file);
			break;
			case 'png':
				$output_image = imagepng($process, $this->resized_file);
			break;
			case 'jpg':
				$output_image = imagejpeg($process, $this->resized_file, 90);
			break;
		}
		
		if(!$output_image) {
			throw new ImageresizeException("Can't create final output image", 220);
		}
		
		// Remove the temp files
		imagedestroy($process); 
		imagedestroy($src);	
	}

}

class ImageresizeException extends Exception {}