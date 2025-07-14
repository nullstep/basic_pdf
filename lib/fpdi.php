<?php

/*

	fpdi.php - single file version

*/

class Rectangle {
	protected $llx;
	protected $lly;
	protected $urx;
	protected $ury;

	public static function byPdfArray($array, PdfParser $parser)
	{
		$array = PdfArray::ensure(PdfType::resolve($array, $parser), 4)->value;
		$ax = PdfNumeric::ensure(PdfType::resolve($array[0], $parser))->value;
		$ay = PdfNumeric::ensure(PdfType::resolve($array[1], $parser))->value;
		$bx = PdfNumeric::ensure(PdfType::resolve($array[2], $parser))->value;
		$by = PdfNumeric::ensure(PdfType::resolve($array[3], $parser))->value;

		return new self($ax, $ay, $bx, $by);
	}

	public static function byVectors(Vector $ll, Vector $ur)
	{
		return new self($ll->getX(), $ll->getY(), $ur->getX(), $ur->getY());
	}

	public function __construct($ax, $ay, $bx, $by)
	{
		$this->llx = \min($ax, $bx);
		$this->lly = \min($ay, $by);
		$this->urx = \max($ax, $bx);
		$this->ury = \max($ay, $by);
	}

	public function getWidth()
	{
		return $this->urx - $this->llx;
	}

	public function getHeight()
	{
		return $this->ury - $this->lly;
	}

	public function getLlx()
	{
		return $this->llx;
	}

	public function getLly()
	{
		return $this->lly;
	}

	public function getUrx()
	{
		return $this->urx;
	}

	public function getUry()
	{
		return $this->ury;
	}

	public function toArray()
	{
		return [
			$this->llx,
			$this->lly,
			$this->urx,
			$this->ury
		];
	}

	public function toPdfArray()
	{
		$array = new PdfArray();
		$array->value[] = PdfNumeric::create($this->llx);
		$array->value[] = PdfNumeric::create($this->lly);
		$array->value[] = PdfNumeric::create($this->urx);
		$array->value[] = PdfNumeric::create($this->ury);

		return $array;
	}
}

// --- math

class Vector {
	protected $x;
	protected $y;

	public function __construct($x = .0, $y = .0)
	{
		$this->x = (float)$x;
		$this->y = (float)$y;
	}

	public function getX()
	{
		return $this->x;
	}

	public function getY()
	{
		return $this->y;
	}

	public function multiplyWithMatrix(Matrix $matrix)
	{
		list($a, $b, $c, $d, $e, $f) = $matrix->getValues();
		$x = $a * $this->x + $c * $this->y + $e;
		$y = $b * $this->x + $d * $this->y + $f;

		return new self($x, $y);
	}
}

class Matrix {
	protected $a;
	protected $b;
	protected $c;
	protected $d;
	protected $e;
	protected $f;

	public function __construct($a = 1, $b = 0, $c = 0, $d = 1, $e = 0, $f = 0)
	{
		$this->a = (float)$a;
		$this->b = (float)$b;
		$this->c = (float)$c;
		$this->d = (float)$d;
		$this->e = (float)$e;
		$this->f = (float)$f;
	}

	public function getValues()
	{
		return [$this->a, $this->b, $this->c, $this->d, $this->e, $this->f];
	}

	public function multiply(self $by)
	{
		$a =
			$this->a * $by->a
			+ $this->b * $by->c
			//+ 0 * $by->e
		;

		$b =
			$this->a * $by->b
			+ $this->b * $by->d
			//+ 0 * $by->f
		;

		$c =
			$this->c * $by->a
			+ $this->d * $by->c
			//+ 0 * $by->e
		;

		$d =
			$this->c * $by->b
			+ $this->d * $by->d
			//+ 0 * $by->f
		;

		$e =
			$this->e * $by->a
			+ $this->f * $by->c
			+ /*1 * */$by->e;

		$f =
			$this->e * $by->b
			+ $this->f * $by->d
			+ /*1 * */$by->f;

		return new self($a, $b, $c, $d, $e, $f);
	}
}

// ---

class GraphicsState {
	protected $ctm;

	public function __construct($ctm = null)
	{
		if ($ctm === null) {
			$ctm = new Matrix();
		} elseif (!($ctm instanceof Matrix)) {
			throw new \InvalidArgumentException('$ctm must be an instance of Fpdi\\Matrix or null');
		}

		$this->ctm = $ctm;
	}

	public function add(Matrix $matrix)
	{
		$this->ctm = $matrix->multiply($this->ctm);
		return $this;
	}

	public function rotate($x, $y, $angle)
	{
		if (abs($angle) < 1e-5) {
			return  $this;
		}

		$angle = deg2rad($angle);
		$c = cos($angle);
		$s = sin($angle);

		$this->add(new Matrix($c, $s, -$s, $c, $x, $y));

		return $this->translate(-$x, -$y);
	}

	public function translate($shiftX, $shiftY)
	{
		return $this->add(new Matrix(1, 0, 0, 1, $shiftX, $shiftY));
	}

	public function scale($scaleX, $scaleY)
	{
		return $this->add(new Matrix($scaleX, 0, 0, $scaleY, 0, 0));
	}

	public function toUserSpace(Vector $vector)
	{
		return $vector->multiplyWithMatrix($this->ctm);
	}
}

// --- cross reference

interface ReaderInterface {
	public function getOffsetFor($objectNumber);

	public function getTrailer();
}

class CrossReference {
	public static $trailerSearchLength = 5500;
	protected $fileHeaderOffset = 0;
	protected $parser;
	protected $readers = [];

	public function __construct(PdfParser $parser, $fileHeaderOffset = 0)
	{
		$this->parser = $parser;
		$this->fileHeaderOffset = $fileHeaderOffset;

		$offset = $this->findStartXref();
		$reader = null;
		/** @noinspection TypeUnsafeComparisonInspection */
		while ($offset != false) { // By doing an unsafe comparsion we ignore faulty references to byte offset 0
			try {
				$reader = $this->readXref($offset + $this->fileHeaderOffset);
			} catch (CrossReferenceException $e) {
				// sometimes the file header offset is part of the byte offsets, so let's retry by resetting it to zero.
				if ($e->getCode() === CrossReferenceException::INVALID_DATA && $this->fileHeaderOffset !== 0) {
					$this->fileHeaderOffset = 0;
					$reader = $this->readXref($offset);
				} else {
					throw $e;
				}
			}

			$trailer = $reader->getTrailer();
			$this->checkForEncryption($trailer);
			$this->readers[] = $reader;

			if (isset($trailer->value['Prev'])) {
				$offset = $trailer->value['Prev']->value;
			} else {
				$offset = false;
			}
		}

		// fix faulty sub-section header
		if ($reader instanceof FixedReader) {
			$reader->fixFaultySubSectionShift();
		}

		if ($reader === null) {
			throw new CrossReferenceException('No cross-reference found.', CrossReferenceException::NO_XREF_FOUND);
		}
	}

	public function getSize()
	{
		return $this->getTrailer()->value['Size']->value;
	}

	public function getTrailer()
	{
		return $this->readers[0]->getTrailer();
	}

	public function getReaders()
	{
		return $this->readers;
	}

	public function getOffsetFor($objectNumber)
	{
		foreach ($this->getReaders() as $reader) {
			$offset = $reader->getOffsetFor($objectNumber);
			if ($offset !== false) {
				return $offset;
			}
		}

		return false;
	}

	public function getIndirectObject($objectNumber)
	{
		$offset = $this->getOffsetFor($objectNumber);
		if ($offset === false) {
			throw new CrossReferenceException(
				\sprintf('Object (id:%s) not found.', $objectNumber),
				CrossReferenceException::OBJECT_NOT_FOUND
			);
		}

		$parser = $this->parser;

		$parser->getTokenizer()->clearStack();
		$parser->getStreamReader()->reset($offset + $this->fileHeaderOffset);

		try {
			/** @var PdfIndirectObject $object */
			$object = $parser->readValue(null, PdfIndirectObject::class);
		} catch (PdfTypeException $e) {
			throw new CrossReferenceException(
				\sprintf('Object (id:%s) not found at location (%s).', $objectNumber, $offset),
				CrossReferenceException::OBJECT_NOT_FOUND,
				$e
			);
		}

		if ($object->objectNumber !== $objectNumber) {
			throw new CrossReferenceException(
				\sprintf('Wrong object found, got %s while %s was expected.', $object->objectNumber, $objectNumber),
				CrossReferenceException::OBJECT_NOT_FOUND
			);
		}

		return $object;
	}

	protected function readXref($offset)
	{
		$this->parser->getStreamReader()->reset($offset);
		$this->parser->getTokenizer()->clearStack();
		$initValue = $this->parser->readValue();

		return $this->initReaderInstance($initValue);
	}

	protected function initReaderInstance($initValue)
	{
		$position = $this->parser->getStreamReader()->getPosition()
			+ $this->parser->getStreamReader()->getOffset() + $this->fileHeaderOffset;

		if ($initValue instanceof PdfToken && $initValue->value === 'xref') {
			try {
				return new FixedReader($this->parser);
			} catch (CrossReferenceException $e) {
				$this->parser->getStreamReader()->reset($position);
				$this->parser->getTokenizer()->clearStack();

				return new LineReader($this->parser);
			}
		}

		if ($initValue instanceof PdfIndirectObject) {
			try {
				$stream = PdfStream::ensure($initValue->value);
			} catch (PdfTypeException $e) {
				throw new CrossReferenceException(
					'Invalid object type at xref reference offset.',
					CrossReferenceException::INVALID_DATA,
					$e
				);
			}

			$type = PdfDictionary::get($stream->value, 'Type');
			if ($type->value !== 'XRef') {
				throw new CrossReferenceException(
					'The xref position points to an incorrect object type.',
					CrossReferenceException::INVALID_DATA
				);
			}

			$this->checkForEncryption($stream->value);

			throw new CrossReferenceException(
				'This PDF document probably uses a compression technique which is not supported by the ' .
				'free parser shipped with FPDI. (See https://www.setasign.com/fpdi-pdf-parser for more details)',
				CrossReferenceException::COMPRESSED_XREF
			);
		}

		throw new CrossReferenceException(
			'The xref position points to an incorrect object type.',
			CrossReferenceException::INVALID_DATA
		);
	}

	protected function checkForEncryption(PdfDictionary $dictionary)
	{
		if (isset($dictionary->value['Encrypt'])) {
			throw new CrossReferenceException(
				'This PDF document is encrypted and cannot be processed with FPDI.',
				CrossReferenceException::ENCRYPTED
			);
		}
	}

	protected function findStartXref()
	{
		$reader = $this->parser->getStreamReader();
		$reader->reset(-self::$trailerSearchLength, self::$trailerSearchLength);

		$buffer = $reader->getBuffer(false);
		$pos = \strrpos($buffer, 'startxref');
		$addOffset = 9;
		if ($pos === false) {
			// Some corrupted documents uses startref, instead of startxref
			$pos = \strrpos($buffer, 'startref');
			if ($pos === false) {
				throw new CrossReferenceException(
					'Unable to find pointer to xref table',
					CrossReferenceException::NO_STARTXREF_FOUND
				);
			}
			$addOffset = 8;
		}

		$reader->setOffset($pos + $addOffset);

		try {
			$value = $this->parser->readValue(null, PdfNumeric::class);
		} catch (PdfTypeException $e) {
			throw new CrossReferenceException(
				'Invalid data after startxref keyword.',
				CrossReferenceException::INVALID_DATA,
				$e
			);
		}

		return $value->value;
	}
}

abstract class AbstractReader {
	protected $parser;
	protected $trailer;

	public function __construct(PdfParser $parser)
	{
		$this->parser = $parser;
		$this->readTrailer();
	}

	public function getTrailer()
	{
		return $this->trailer;
	}

	protected function readTrailer()
	{
		try {
			$trailerKeyword = $this->parser->readValue(null, PdfToken::class);
			if ($trailerKeyword->value !== 'trailer') {
				throw new CrossReferenceException(
					\sprintf(
						'Unexpected end of cross reference. "trailer"-keyword expected, got: %s.',
						$trailerKeyword->value
					),
					CrossReferenceException::UNEXPECTED_END
				);
			}
		} catch (PdfTypeException $e) {
			throw new CrossReferenceException(
				'Unexpected end of cross reference. "trailer"-keyword expected, got an invalid object type.',
				CrossReferenceException::UNEXPECTED_END,
				$e
			);
		}

		try {
			$trailer = $this->parser->readValue(null, PdfDictionary::class);
		} catch (PdfTypeException $e) {
			throw new CrossReferenceException(
				'Unexpected end of cross reference. Trailer not found.',
				CrossReferenceException::UNEXPECTED_END,
				$e
			);
		}

		$this->trailer = $trailer;
	}
}

class FixedReader extends AbstractReader implements ReaderInterface {
	protected $reader;
	protected $subSections;

	public function __construct(PdfParser $parser)
	{
		$this->reader = $parser->getStreamReader();
		$this->read();
		parent::__construct($parser);
	}

	public function getSubSections()
	{
		return $this->subSections;
	}

	public function getOffsetFor($objectNumber)
	{
		foreach ($this->subSections as $offset => list($startObject, $objectCount)) {
			/**
			 * @var int $startObject
			 * @var int $objectCount
			 */
			if ($objectNumber >= $startObject && $objectNumber < ($startObject + $objectCount)) {
				$position = $offset + 20 * ($objectNumber - $startObject);
				$this->reader->ensure($position, 20);
				$line = $this->reader->readBytes(20);
				if ($line[17] === 'f') {
					return false;
				}

				return (int) \substr($line, 0, 10);
			}
		}

		return false;
	}

	protected function read()
	{
		$subSections = [];

		$startObject = $entryCount = $lastLineStart = null;
		$validityChecked = false;
		while (($line = $this->reader->readLine(20)) !== false) {
			if (\strpos($line, 'trailer') !== false) {
				$this->reader->reset($lastLineStart);
				break;
			}

			// jump over if line content doesn't match the expected string
			if (\sscanf($line, '%d %d', $startObject, $entryCount) !== 2) {
				continue;
			}

			$oldPosition = $this->reader->getPosition();
			$position = $oldPosition + $this->reader->getOffset();

			if (!$validityChecked && $entryCount > 0) {
				$nextLine = $this->reader->readBytes(21);
				/* Check the next line for maximum of 20 bytes and not longer
				 * By catching 21 bytes and trimming the length should be still 21.
				 */
				if (\strlen(\trim($nextLine)) !== 21) {
					throw new CrossReferenceException(
						'Cross-reference entries are larger than 20 bytes.',
						CrossReferenceException::ENTRIES_TOO_LARGE
					);
				}

				/* Check for less than 20 bytes: cut the line to 20 bytes and trim; have to result in exactly 18 bytes.
				 * If it would have less bytes the substring would get the first bytes of the next line which would
				 * evaluate to a 20 bytes long string after trimming.
				 */
				if (\strlen(\trim(\substr($nextLine, 0, 20))) !== 18) {
					throw new CrossReferenceException(
						'Cross-reference entries are less than 20 bytes.',
						CrossReferenceException::ENTRIES_TOO_SHORT
					);
				}

				$validityChecked = true;
			}

			$subSections[$position] = [$startObject, $entryCount];

			$lastLineStart = $position + $entryCount * 20;
			$this->reader->reset($lastLineStart);
		}

		// reset after the last correct parsed line
		$this->reader->reset($lastLineStart);

		if (\count($subSections) === 0) {
			throw new CrossReferenceException(
				'No entries found in cross-reference.',
				CrossReferenceException::NO_ENTRIES
			);
		}

		$this->subSections = $subSections;
	}

	public function fixFaultySubSectionShift()
	{
		$subSections = $this->getSubSections();
		if (\count($subSections) > 1) {
			return false;
		}

		$subSection = \current($subSections);
		if ($subSection[0] != 1) {
			return false;
		}

		if ($this->getOffsetFor(1) === false) {
			foreach ($subSections as $offset => list($startObject, $objectCount)) {
				$this->subSections[$offset] = [$startObject - 1, $objectCount];
			}
			return true;
		}

		return false;
	}
}

class LineReader extends AbstractReader implements ReaderInterface {
	protected $offsets;

	public function __construct(PdfParser $parser)
	{
		$this->read($this->extract($parser->getStreamReader()));
		parent::__construct($parser);
	}

	public function getOffsetFor($objectNumber)
	{
		if (isset($this->offsets[$objectNumber])) {
			return $this->offsets[$objectNumber][0];
		}

		return false;
	}

	public function getOffsets()
	{
		return $this->offsets;
	}

	protected function extract(StreamReader $reader)
	{
		$bytesPerCycle = 100;
		$reader->reset(null, $bytesPerCycle);

		$cycles = 0;
		do {
			// 6 = length of "trailer" - 1
			$pos = \max(($bytesPerCycle * $cycles) - 6, 0);
			$trailerPos = \strpos($reader->getBuffer(false), 'trailer', $pos);
			$cycles++;
		} while ($trailerPos === false && $reader->increaseLength($bytesPerCycle) !== false);

		if ($trailerPos === false) {
			throw new CrossReferenceException(
				'Unexpected end of cross reference. "trailer"-keyword not found.',
				CrossReferenceException::NO_TRAILER_FOUND
			);
		}

		$xrefContent = \substr($reader->getBuffer(false), 0, $trailerPos);
		$reader->reset($reader->getPosition() + $trailerPos);

		return $xrefContent;
	}

	protected function read($xrefContent)
	{
		// get eol markers in the first 100 bytes
		\preg_match_all("/(\r\n|\n|\r)/", \substr($xrefContent, 0, 100), $m);

		if (\count($m[0]) === 0) {
			throw new CrossReferenceException(
				'No data found in cross-reference.',
				CrossReferenceException::INVALID_DATA
			);
		}

		// count(array_count_values()) is faster then count(array_unique())
		// @see https://github.com/symfony/symfony/pull/23731
		// can be reverted for php7.2
		$differentLineEndings = \count(\array_count_values($m[0]));
		if ($differentLineEndings > 1) {
			$lines = \preg_split("/(\r\n|\n|\r)/", $xrefContent, -1, PREG_SPLIT_NO_EMPTY);
		} else {
			$lines = \explode($m[0][0], $xrefContent);
		}

		unset($differentLineEndings, $m);
		if (!\is_array($lines)) {
			$this->offsets = [];
			return;
		}

		$start = 0;
		$offsets = [];

		// trim all lines and remove empty lines
		$lines = \array_filter(\array_map('\trim', $lines));
		foreach ($lines as $line) {
			$pieces = \explode(' ', $line);

			switch (\count($pieces)) {
				case 2:
					$start = (int) $pieces[0];
					break;

				case 3:
					switch ($pieces[2]) {
						case 'n':
							$offsets[$start] = [(int) $pieces[0], (int) $pieces[1]];
							$start++;
							break 2;
						case 'f':
							$start++;
							break 2;
					}
					// fall through if pieces doesn't match

				default:
					throw new CrossReferenceException(
						\sprintf('Unexpected data in xref table (%s)', \implode(' ', $pieces)),
						CrossReferenceException::INVALID_DATA
					);
			}
		}

		$this->offsets = $offsets;
	}
}

// --- filter

interface FilterInterface {
	public function decode($data);
}

class Ascii85 implements FilterInterface {
	public function decode($data)
	{
		$out = '';
		$state = 0;
		$chn = null;

		$data = \preg_replace('/\s/', '', $data);

		$l = \strlen($data);

		/** @noinspection ForeachInvariantsInspection */
		for ($k = 0; $k < $l; ++$k) {
			$ch = \ord($data[$k]) & 0xff;

			//Start <~
			if ($k === 0 && $ch === 60 && isset($data[$k + 1]) && (\ord($data[$k + 1]) & 0xFF) === 126) {
				$k++;
				continue;
			}
			//End ~>
			if ($ch === 126 && isset($data[$k + 1]) && (\ord($data[$k + 1]) & 0xFF) === 62) {
				break;
			}

			if ($ch === 122 /* z */ && $state === 0) {
				$out .= \chr(0) . \chr(0) . \chr(0) . \chr(0);
				continue;
			}

			if ($ch < 33 /* ! */ || $ch > 117 /* u */) {
				throw new Ascii85Exception(
					'Illegal character found while ASCII85 decode.',
					Ascii85Exception::ILLEGAL_CHAR_FOUND
				);
			}

			$chn[$state] = $ch - 33;/* ! */
			$state++;

			if ($state === 5) {
				$state = 0;
				$r = 0;
				for ($j = 0; $j < 5; ++$j) {
					/** @noinspection UnnecessaryCastingInspection */
					$r = (int)($r * 85 + $chn[$j]);
				}

				$out .= \chr($r >> 24)
					. \chr($r >> 16)
					. \chr($r >> 8)
					. \chr($r);
			}
		}

		if ($state === 1) {
			throw new Ascii85Exception(
				'Illegal length while ASCII85 decode.',
				Ascii85Exception::ILLEGAL_LENGTH
			);
		}

		if ($state === 2) {
			$r = $chn[0] * 85 * 85 * 85 * 85 + ($chn[1] + 1) * 85 * 85 * 85;
			$out .= \chr($r >> 24);
		} elseif ($state === 3) {
			$r = $chn[0] * 85 * 85 * 85 * 85 + $chn[1] * 85 * 85 * 85 + ($chn[2] + 1) * 85 * 85;
			$out .= \chr($r >> 24);
			$out .= \chr($r >> 16);
		} elseif ($state === 4) {
			$r = $chn[0] * 85 * 85 * 85 * 85 + $chn[1] * 85 * 85 * 85 + $chn[2] * 85 * 85 + ($chn[3] + 1) * 85;
			$out .= \chr($r >> 24);
			$out .= \chr($r >> 16);
			$out .= \chr($r >> 8);
		}

		return $out;
	}
}

class AsciiHex implements FilterInterface {
	public function decode($data)
	{
		$data = \preg_replace('/[^0-9A-Fa-f]/', '', \rtrim($data, '>'));
		if ((\strlen($data) % 2) === 1) {
			$data .= '0';
		}

		return \pack('H*', $data);
	}

	public function encode($data, $leaveEOD = false)
	{
		$t = \unpack('H*', $data);
		return \current($t)
			. ($leaveEOD ? '' : '>');
	}
}

class Flate implements FilterInterface {
	protected function extensionLoaded()
	{
		return \extension_loaded('zlib');
	}

	public function decode($data)
	{
		if ($this->extensionLoaded()) {
			$oData = $data;
			$data = (($data !== '') ? @\gzuncompress($data) : '');
			if ($data === false) {
				// let's try if the checksum is CRC32
				$fh = fopen('php://temp', 'w+b');
				fwrite($fh, "\x1f\x8b\x08\x00\x00\x00\x00\x00" . $oData);
				// "window" == 31 -> 16 + (8 to 15): Uses the low 4 bits of the value as the window size logarithm.
				//                   The input must include a gzip header and trailer (via 16).
				stream_filter_append($fh, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 31]);
				fseek($fh, 0);
				$data = @stream_get_contents($fh);
				fclose($fh);

				if ($data) {
					return $data;
				}

				// Try this fallback (remove the zlib stream header)
				$data = @(gzinflate(substr($oData, 2)));

				if ($data === false) {
					throw new FlateException(
						'Error while decompressing stream.',
						FlateException::DECOMPRESS_ERROR
					);
				}
			}
		} else {
			throw new FlateException(
				'To handle FlateDecode filter, enable zlib support in PHP.',
				FlateException::NO_ZLIB
			);
		}

		return $data;
	}
}

class Lzw implements FilterInterface {
	protected $data;
	protected $sTable = [];
	protected $dataLength = 0;
	protected $tIdx;
	protected $bitsToGet = 9;
	protected $bytePointer;
	protected $nextData = 0;
	protected $nextBits = 0;
	protected $andTable = [511, 1023, 2047, 4095];

	public function decode($data)
	{
		if ($data[0] === "\x00" && $data[1] === "\x01") {
			throw new LzwException(
				'LZW flavour not supported.',
				LzwException::LZW_FLAVOUR_NOT_SUPPORTED
			);
		}

		$this->initsTable();

		$this->data = $data;
		$this->dataLength = \strlen($data);

		// Initialize pointers
		$this->bytePointer = 0;

		$this->nextData = 0;
		$this->nextBits = 0;

		$prevCode = 0;

		$uncompData = '';

		while (($code = $this->getNextCode()) !== 257) {
			if ($code === 256) {
				$this->initsTable();
			} elseif ($prevCode === 256) {
				$uncompData .= $this->sTable[$code];
			} elseif ($code < $this->tIdx) {
				$string = $this->sTable[$code];
				$uncompData .= $string;

				$this->addStringToTable($this->sTable[$prevCode], $string[0]);
			} else {
				$string = $this->sTable[$prevCode];
				$string .= $string[0];
				$uncompData .= $string;

				$this->addStringToTable($string);
			}
			$prevCode = $code;
		}

		return $uncompData;
	}

	protected function initsTable()
	{
		$this->sTable = [];

		for ($i = 0; $i < 256; $i++) {
			$this->sTable[$i] = \chr($i);
		}

		$this->tIdx = 258;
		$this->bitsToGet = 9;
	}

	protected function addStringToTable($oldString, $newString = '')
	{
		$string = $oldString . $newString;

		// Add this new String to the table
		$this->sTable[$this->tIdx++] = $string;

		if ($this->tIdx === 511) {
			$this->bitsToGet = 10;
		} elseif ($this->tIdx === 1023) {
			$this->bitsToGet = 11;
		} elseif ($this->tIdx === 2047) {
			$this->bitsToGet = 12;
		}
	}

	protected function getNextCode()
	{
		if ($this->bytePointer === $this->dataLength) {
			return 257;
		}

		$this->nextData = ($this->nextData << 8) | (\ord($this->data[$this->bytePointer++]) & 0xff);
		$this->nextBits += 8;

		if ($this->nextBits < $this->bitsToGet) {
			$this->nextData = ($this->nextData << 8) | (\ord($this->data[$this->bytePointer++]) & 0xff);
			$this->nextBits += 8;
		}

		$code = ($this->nextData >> ($this->nextBits - $this->bitsToGet)) & $this->andTable[$this->bitsToGet - 9];
		$this->nextBits -= $this->bitsToGet;

		return $code;
	}
}

// --- exception

class FpdiException extends \Exception {
}

class PdfParserException extends FpdiException {
	const NOT_IMPLEMENTED = 0x0001;
	const IMPLEMENTED_IN_FPDI_PDF_PARSER = 0x0002;
	const INVALID_DATA_TYPE = 0x0003;
	const FILE_HEADER_NOT_FOUND = 0x0004;
	const PDF_VERSION_NOT_FOUND = 0x0005;
	const INVALID_DATA_SIZE = 0x0006;
}

class PdfTypeException extends PdfParserException {
	const NO_NEWLINE_AFTER_STREAM_KEYWORD = 0x0601;
}

class CrossReferenceException extends PdfParserException {
	const INVALID_DATA = 0x0101;
	const XREF_MISSING = 0x0102;
	const ENTRIES_TOO_LARGE = 0x0103;
	const ENTRIES_TOO_SHORT = 0x0104;
	const NO_ENTRIES = 0x0105;
	const NO_TRAILER_FOUND = 0x0106;
	const NO_STARTXREF_FOUND = 0x0107;
	const NO_XREF_FOUND = 0x0108;
	const UNEXPECTED_END = 0x0109;
	const OBJECT_NOT_FOUND = 0x010A;
	const COMPRESSED_XREF = 0x010B;
	const ENCRYPTED = 0x010C;
}

class FilterException extends PdfParserException {
	const UNSUPPORTED_FILTER = 0x0201;
	const NOT_IMPLEMENTED = 0x0202;
}

class Ascii85Exception extends FilterException {
	const ILLEGAL_CHAR_FOUND = 0x0301;
	const ILLEGAL_LENGTH = 0x0302;
}

class FlateException extends FilterException {
	const NO_ZLIB = 0x0401;
	const DECOMPRESS_ERROR = 0x0402;
}

class LzwException extends FilterException {
	const LZW_FLAVOUR_NOT_SUPPORTED = 0x0501;
}

class PdfReaderException extends FpdiException {
	const KIDS_EMPTY = 0x0101;
	const UNEXPECTED_DATA_TYPE = 0x0102;
	const MISSING_DATA = 0x0103;
}


// --- pdftypes

class PdfType {
	public static function resolve(PdfType $value, PdfParser $parser, $stopAtIndirectObject = false)
	{
		if ($value instanceof PdfIndirectObject) {
			if ($stopAtIndirectObject === true) {
				return $value;
			}

			return self::resolve($value->value, $parser, $stopAtIndirectObject);
		}

		if ($value instanceof PdfIndirectObjectReference) {
			return self::resolve($parser->getIndirectObject($value->value), $parser, $stopAtIndirectObject);
		}

		return $value;
	}

	protected static function ensureType($type, $value, $errorMessage)
	{
		if (!($value instanceof $type)) {
			throw new PdfTypeException(
				$errorMessage,
				PdfTypeException::INVALID_DATA_TYPE
			);
		}

		return $value;
	}

	public static function flatten(PdfType $value, PdfParser $parser)
	{
		if ($value instanceof PdfIndirectObjectReference) {
			return self::flatten(self::resolve($value, $parser), $parser);
		}

		if ($value instanceof PdfDictionary || $value instanceof PdfArray) {
			foreach ($value->value as $key => $_value) {
				$value->value[$key] = self::flatten($_value, $parser);
			}
		}

		if ($value instanceof PdfStream) {
			throw new PdfTypeException('There is a stream object found which cannot be flattened to a direct object.');
		}

		return $value;
	}

	public $value;
}

class PdfToken extends PdfType {
	public static function create($token)
	{
		$v = new self();
		$v->value = $token;

		return $v;
	}

	public static function ensure($token)
	{
		return PdfType::ensureType(self::class, $token, 'Token value expected.');
	}
}


class PdfString extends PdfType {
	public static function parse(StreamReader $streamReader)
	{
		$pos = $startPos = $streamReader->getOffset();
		$openBrackets = 1;
		do {
			$buffer = $streamReader->getBuffer(false);
			for ($length = \strlen($buffer); $openBrackets !== 0 && $pos < $length; $pos++) {
				switch ($buffer[$pos]) {
					case '(':
						$openBrackets++;
						break;
					case ')':
						$openBrackets--;
						break;
					case '\\':
						$pos++;
				}
			}
		} while ($openBrackets !== 0 && $streamReader->increaseLength());

		$result = \substr($buffer, $startPos, $openBrackets + $pos - $startPos - 1);
		$streamReader->setOffset($pos);

		$v = new self();
		$v->value = $result;

		return $v;
	}

	public static function create($value)
	{
		$v = new self();
		$v->value = $value;

		return $v;
	}

	public static function ensure($string)
	{
		return PdfType::ensureType(self::class, $string, 'String value expected.');
	}

	public static function escape($s)
	{
		// Still a bit faster, than direct replacing
		if (
			\strpos($s, '\\') !== false ||
			\strpos($s, ')')  !== false ||
			\strpos($s, '(')  !== false ||
			\strpos($s, "\x0D") !== false ||
			\strpos($s, "\x0A") !== false ||
			\strpos($s, "\x09") !== false ||
			\strpos($s, "\x08") !== false ||
			\strpos($s, "\x0C") !== false
		) {
			// is faster than strtr(...)
			return \str_replace(
				['\\',   ')',   '(',   "\x0D", "\x0A", "\x09", "\x08", "\x0C"],
				['\\\\', '\\)', '\\(', '\r',   '\n',   '\t',   '\b',   '\f'],
				$s
			);
		}

		return $s;
	}

	public static function unescape($s)
	{
		$out = '';
		/** @noinspection ForeachInvariantsInspection */
		for ($count = 0, $n = \strlen($s); $count < $n; $count++) {
			if ($s[$count] !== '\\') {
				$out .= $s[$count];
			} else {
				// A backslash at the end of the string - ignore it
				if ($count === ($n - 1)) {
					break;
				}

				switch ($s[++$count]) {
					case ')':
					case '(':
					case '\\':
						$out .= $s[$count];
						break;

					case 'f':
						$out .= "\x0C";
						break;

					case 'b':
						$out .= "\x08";
						break;

					case 't':
						$out .= "\x09";
						break;

					case 'r':
						$out .= "\x0D";
						break;

					case 'n':
						$out .= "\x0A";
						break;

					case "\r":
						if ($count !== $n - 1 && $s[$count + 1] === "\n") {
							$count++;
						}
						break;

					case "\n":
						break;

					default:
						$actualChar = \ord($s[$count]);
						// ascii 48 = number 0
						// ascii 57 = number 9
						if ($actualChar >= 48 && $actualChar <= 57) {
							$oct = '' . $s[$count];

							/** @noinspection NotOptimalIfConditionsInspection */
							if (
								$count + 1 < $n
								&& \ord($s[$count + 1]) >= 48
								&& \ord($s[$count + 1]) <= 57
							) {
								$count++;
								$oct .= $s[$count];

								/** @noinspection NotOptimalIfConditionsInspection */
								if (
									$count + 1 < $n
									&& \ord($s[$count + 1]) >= 48
									&& \ord($s[$count + 1]) <= 57
								) {
									$oct .= $s[++$count];
								}
							}

							$out .= \chr(\octdec($oct));
						} else {
							// If the character is not one of those defined, the backslash is ignored
							$out .= $s[$count];
						}
				}
			}
		}
		return $out;
	}
}


class PdfStream extends PdfType {
	public static function parse(PdfDictionary $dictionary, StreamReader $reader, $parser = null)
	{
		if ($parser !== null && !($parser instanceof PdfParser)) {
			throw new \InvalidArgumentException('$parser must be an instance of PdfParser or null');
		}
		$v = new self();
		$v->value = $dictionary;
		$v->reader = $reader;
		$v->parser = $parser;

		$offset = $reader->getOffset();

		// Find the first "newline"
		while (($firstByte = $reader->getByte($offset)) !== false) {
			$offset++;
			if ($firstByte === "\n" || $firstByte === "\r") {
				break;
			}
		}

		if ($firstByte === false) {
			throw new PdfTypeException(
				'Unable to parse stream data. No newline after the stream keyword found.',
				PdfTypeException::NO_NEWLINE_AFTER_STREAM_KEYWORD
			);
		}

		$sndByte = $reader->getByte($offset);
		if ($sndByte === "\n" && $firstByte !== "\n") {
			$offset++;
		}

		$reader->setOffset($offset);
		// let's only save the byte-offset and read the stream only when needed
		$v->stream = $reader->getPosition() + $reader->getOffset();

		return $v;
	}

	public static function create(PdfDictionary $dictionary, $stream)
	{
		$v = new self();
		$v->value = $dictionary;
		$v->stream = (string) $stream;

		return $v;
	}

	public static function ensure($stream)
	{
		return PdfType::ensureType(self::class, $stream, 'Stream value expected.');
	}

	protected $stream;
	protected $reader;
	protected $parser;

	public function getStream($cache = false)
	{
		if (\is_int($this->stream)) {
			$length = PdfDictionary::get($this->value, 'Length');
			if ($this->parser !== null) {
				$length = PdfType::resolve($length, $this->parser);
			}

			if (!($length instanceof PdfNumeric) || $length->value === 0) {
				$this->reader->reset($this->stream, 100000);
				$buffer = $this->extractStream();
			} else {
				$this->reader->reset($this->stream, $length->value);
				$buffer = $this->reader->getBuffer(false);
				if ($this->parser !== null) {
					$this->reader->reset($this->stream + strlen($buffer));
					$this->parser->getTokenizer()->clearStack();
					$token = $this->parser->readValue();
					if ($token === false || !($token instanceof PdfToken) || $token->value !== 'endstream') {
						$this->reader->reset($this->stream, 100000);
						$buffer = $this->extractStream();
						$this->reader->reset($this->stream + strlen($buffer));
					}
				}
			}

			if ($cache === false) {
				return $buffer;
			}

			$this->stream = $buffer;
			$this->reader = null;
		}

		return $this->stream;
	}

	protected function extractStream()
	{
		while (true) {
			$buffer = $this->reader->getBuffer(false);
			$length = \strpos($buffer, 'endstream');
			if ($length === false) {
				if (!$this->reader->increaseLength(100000)) {
					throw new PdfTypeException('Cannot extract stream.');
				}
				continue;
			}
			break;
		}

		$buffer = \substr($buffer, 0, $length);
		$lastByte = \substr($buffer, -1);

		/* Check for EOL marker =
		 *   CARRIAGE RETURN (\r) and a LINE FEED (\n) or just a LINE FEED (\n},
		 *   and not by a CARRIAGE RETURN (\r) alone
		 */
		if ($lastByte === "\n") {
			$buffer = \substr($buffer, 0, -1);

			$lastByte = \substr($buffer, -1);
			if ($lastByte === "\r") {
				$buffer = \substr($buffer, 0, -1);
			}
		}

		// There are streams in the wild, which have only white signs in them but need to be parsed manually due
		// to a problem encountered before (e.g. Length === 0). We should set them to empty streams to avoid problems
		// in further processing (e.g. applying of filters).
		if (trim($buffer) === '') {
			$buffer = '';
		}

		return $buffer;
	}

	public function getFilters()
	{
		$filters = PdfDictionary::get($this->value, 'Filter');
		if ($filters instanceof PdfNull) {
			return [];
		}

		if ($filters instanceof PdfArray) {
			$filters = $filters->value;
		} else {
			$filters = [$filters];
		}

		return $filters;
	}

	public function getUnfilteredStream()
	{
		$stream = $this->getStream();
		$filters = $this->getFilters();
		if ($filters === []) {
			return $stream;
		}

		$decodeParams = PdfDictionary::get($this->value, 'DecodeParms');
		if ($decodeParams instanceof PdfArray) {
			$decodeParams = $decodeParams->value;
		} else {
			$decodeParams = [$decodeParams];
		}

		foreach ($filters as $key => $filter) {
			if (!($filter instanceof PdfName)) {
				continue;
			}

			$decodeParam = null;
			if (isset($decodeParams[$key])) {
				$decodeParam = ($decodeParams[$key] instanceof PdfDictionary ? $decodeParams[$key] : null);
			}

			switch ($filter->value) {
				case 'FlateDecode':
				case 'Fl':
				case 'LZWDecode':
				case 'LZW':
					if (\strpos($filter->value, 'LZW') === 0) {
						$filterObject = new Lzw();
					} else {
						$filterObject = new Flate();
					}

					$stream = $filterObject->decode($stream);

					if ($decodeParam instanceof PdfDictionary) {
						$predictor = PdfDictionary::get($decodeParam, 'Predictor', PdfNumeric::create(1));
						if ($predictor->value !== 1) {
							if (!\class_exists(Predictor::class)) {
								throw new PdfParserException(
									'This PDF document makes use of features which are only implemented in the ' .
									'commercial "FPDI PDF-Parser" add-on (see https://www.setasign.com/fpdi-pdf-' .
									'parser).',
									PdfParserException::IMPLEMENTED_IN_FPDI_PDF_PARSER
								);
							}

							$colors = PdfDictionary::get($decodeParam, 'Colors', PdfNumeric::create(1));
							$bitsPerComponent = PdfDictionary::get(
								$decodeParam,
								'BitsPerComponent',
								PdfNumeric::create(8)
							);

							$columns = PdfDictionary::get($decodeParam, 'Columns', PdfNumeric::create(1));

							$filterObject = new Predictor(
								$predictor->value,
								$colors->value,
								$bitsPerComponent->value,
								$columns->value
							);

							$stream = $filterObject->decode($stream);
						}
					}

					break;
				case 'ASCII85Decode':
				case 'A85':
					$filterObject = new Ascii85();
					$stream = $filterObject->decode($stream);
					break;

				case 'ASCIIHexDecode':
				case 'AHx':
					$filterObject = new AsciiHex();
					$stream = $filterObject->decode($stream);
					break;

				case 'Crypt':
					if (!$decodeParam instanceof PdfDictionary) {
						break;
					}
					// Filter is "Identity"
					$name = PdfDictionary::get($decodeParam, 'Name');
					if (!$name instanceof PdfName || $name->value !== 'Identity') {
						break;
					}

					throw new FilterException(
						'Support for Crypt filters other than "Identity" is not implemented.',
						FilterException::UNSUPPORTED_FILTER
					);

				default:
					throw new FilterException(
						\sprintf('Unsupported filter "%s".', $filter->value),
						FilterException::UNSUPPORTED_FILTER
					);
			}
		}

		return $stream;
	}
}


class PdfNumeric extends PdfType {
	public static function create($value)
	{
		$v = new self();
		$v->value = $value + 0;

		return $v;
	}

	public static function ensure($value)
	{
		return PdfType::ensureType(self::class, $value, 'Numeric value expected.');
	}
}


class PdfNull extends PdfType {
	// empty body
}

class PdfName extends PdfType {
	public static function parse(Tokenizer $tokenizer, StreamReader $streamReader)
	{
		$v = new self();
		if (\strspn($streamReader->getByte(), "\x00\x09\x0A\x0C\x0D\x20()<>[]{}/%") === 0) {
			$v->value = (string) $tokenizer->getNextToken();
			return $v;
		}

		$v->value = '';
		return $v;
	}

	public static function unescape($value)
	{
		if (strpos($value, '#') === false) {
			return $value;
		}

		return preg_replace_callback('/#([a-fA-F\d]{2})/', function ($matches) {
			return chr(hexdec($matches[1]));
		}, $value);
	}

	public static function create($string)
	{
		$v = new self();
		$v->value = $string;

		return $v;
	}

	public static function ensure($name)
	{
		return PdfType::ensureType(self::class, $name, 'Name value expected.');
	}
}


class PdfIndirectObjectReference extends PdfType {
	public static function create($objectNumber, $generationNumber)
	{
		$v = new self();
		$v->value = (int) $objectNumber;
		$v->generationNumber = (int) $generationNumber;

		return $v;
	}

	public static function ensure($value)
	{
		return PdfType::ensureType(self::class, $value, 'Indirect reference value expected.');
	}

	public $generationNumber;
}


class PdfIndirectObject extends PdfType {
	public static function parse(
		$objectNumber,
		$objectGenerationNumber,
		PdfParser $parser,
		Tokenizer $tokenizer,
		StreamReader $reader
	) {
		$value = $parser->readValue();
		if ($value === false) {
			return false;
		}

		$nextToken = $tokenizer->getNextToken();
		if ($nextToken === 'stream') {
			$value = PdfStream::parse($value, $reader, $parser);
		} elseif ($nextToken !== false) {
			$tokenizer->pushStack($nextToken);
		}

		$v = new self();
		$v->objectNumber = (int) $objectNumber;
		$v->generationNumber = (int) $objectGenerationNumber;
		$v->value = $value;

		return $v;
	}

	public static function create($objectNumber, $generationNumber, PdfType $value)
	{
		$v = new self();
		$v->objectNumber = (int) $objectNumber;
		$v->generationNumber = (int) $generationNumber;
		$v->value = $value;

		return $v;
	}

	public static function ensure($indirectObject)
	{
		return PdfType::ensureType(self::class, $indirectObject, 'Indirect object expected.');
	}

	public $objectNumber;
	public $generationNumber;
}

class PdfHexString extends PdfType {
	public static function parse(StreamReader $streamReader)
	{
		$bufferOffset = $streamReader->getOffset();

		while (true) {
			$buffer = $streamReader->getBuffer(false);
			$pos = \strpos($buffer, '>', $bufferOffset);
			if ($pos === false) {
				if (!$streamReader->increaseLength()) {
					return false;
				}
				continue;
			}

			break;
		}

		$result = \substr($buffer, $bufferOffset, $pos - $bufferOffset);
		$streamReader->setOffset($pos + 1);

		$v = new self();
		$v->value = $result;

		return $v;
	}

	public static function create($string)
	{
		$v = new self();
		$v->value = $string;

		return $v;
	}

	public static function ensure($hexString)
	{
		return PdfType::ensureType(self::class, $hexString, 'Hex string value expected.');
	}
}


class PdfDictionary extends PdfType {
	public static function parse(Tokenizer $tokenizer, StreamReader $streamReader, PdfParser $parser)
	{
		$entries = [];

		while (true) {
			$token = $tokenizer->getNextToken();
			if ($token === '>' && $streamReader->getByte() === '>') {
				$streamReader->addOffset(1);
				break;
			}

			$key = $parser->readValue($token);
			if ($key === false) {
				return false;
			}

			// ensure the first value to be a Name object
			if (!($key instanceof PdfName)) {
				$lastToken = null;
				// ignore all other entries and search for the closing brackets
				while (($token = $tokenizer->getNextToken()) !== '>' || $lastToken !== '>') {
					if ($token === false) {
						return false;
					}
					$lastToken = $token;
				}

				break;
			}


			$value = $parser->readValue();
			if ($value === false) {
				return false;
			}

			if ($value instanceof PdfNull) {
				continue;
			}

			// catch missing value
			if ($value instanceof PdfToken && $value->value === '>' && $streamReader->getByte() === '>') {
				$streamReader->addOffset(1);
				break;
			}

			$entries[$key->value] = $value;
		}

		$v = new self();
		$v->value = $entries;

		return $v;
	}

	public static function create(array $entries = [])
	{
		$v = new self();
		$v->value = $entries;

		return $v;
	}

	public static function get($dictionary, $key, $default = null)
	{
		if ($default !== null && !($default instanceof PdfType)) {
			throw new \InvalidArgumentException('Default value must be an instance of PdfType or null');
		}
		$dictionary = self::ensure($dictionary);

		if (isset($dictionary->value[$key])) {
			return $dictionary->value[$key];
		}

		return $default === null
			? new PdfNull()
			: $default;
	}

	public static function ensure($dictionary)
	{
		return PdfType::ensureType(self::class, $dictionary, 'Dictionary value expected.');
	}
}


class PdfBoolean extends PdfType {
	public static function create($value)
	{
		$v = new self();
		$v->value = (bool) $value;
		return $v;
	}

	public static function ensure($value)
	{
		return PdfType::ensureType(self::class, $value, 'Boolean value expected.');
	}
}


class PdfArray extends PdfType {
	public static function parse(Tokenizer $tokenizer, PdfParser $parser)
	{
		$result = [];

		// Recurse into this function until we reach the end of the array.
		while (($token = $tokenizer->getNextToken()) !== ']') {
			if ($token === false || ($value = $parser->readValue($token)) === false) {
				return false;
			}

			$result[] = $value;
		}

		$v = new self();
		$v->value = $result;

		return $v;
	}

	public static function create(array $values = [])
	{
		$v = new self();
		$v->value = $values;

		return $v;
	}

	public static function ensure($array, $size = null)
	{
		$result = PdfType::ensureType(self::class, $array, 'Array value expected.');

		if ($size !== null && \count($array->value) !== $size) {
			throw new PdfTypeException(
				\sprintf('Array with %s entries expected.', $size),
				PdfTypeException::INVALID_DATA_SIZE
			);
		}

		return $result;
	}
}

// --- pdf reader

class Page {
	protected $pageObject;
	protected $pageDictionary;
	protected $parser;

	protected $inheritedAttributes;

	public function __construct(PdfIndirectObject $page, PdfParser $parser)
	{
		$this->pageObject = $page;
		$this->parser = $parser;
	}

	public function getPageObject()
	{
		return $this->pageObject;
	}

	public function getPageDictionary()
	{
		if ($this->pageDictionary === null) {
			$this->pageDictionary = PdfDictionary::ensure(PdfType::resolve($this->getPageObject(), $this->parser));
		}

		return $this->pageDictionary;
	}

	public function getAttribute($name, $inherited = true)
	{
		$dict = $this->getPageDictionary();

		if (isset($dict->value[$name])) {
			return $dict->value[$name];
		}

		$inheritedKeys = ['Resources', 'MediaBox', 'CropBox', 'Rotate'];
		if ($inherited && \in_array($name, $inheritedKeys, true)) {
			if ($this->inheritedAttributes === null) {
				$this->inheritedAttributes = [];
				$inheritedKeys = \array_filter($inheritedKeys, function ($key) use ($dict) {
					return !isset($dict->value[$key]);
				});

				if (\count($inheritedKeys) > 0) {
					$parentDict = PdfType::resolve(PdfDictionary::get($dict, 'Parent'), $this->parser);
					while ($parentDict instanceof PdfDictionary) {
						foreach ($inheritedKeys as $index => $key) {
							if (isset($parentDict->value[$key])) {
								$this->inheritedAttributes[$key] = $parentDict->value[$key];
								unset($inheritedKeys[$index]);
							}
						}

						/** @noinspection NotOptimalIfConditionsInspection */
						if (isset($parentDict->value['Parent']) && \count($inheritedKeys) > 0) {
							$parentDict = PdfType::resolve(PdfDictionary::get($parentDict, 'Parent'), $this->parser);
						} else {
							break;
						}
					}
				}
			}

			if (isset($this->inheritedAttributes[$name])) {
				return $this->inheritedAttributes[$name];
			}
		}

		return null;
	}

	public function getRotation()
	{
		$rotation = $this->getAttribute('Rotate');
		if ($rotation === null) {
			return 0;
		}

		$rotation = PdfNumeric::ensure(PdfType::resolve($rotation, $this->parser))->value % 360;

		if ($rotation < 0) {
			$rotation += 360;
		}

		return $rotation;
	}

	public function getBoundary($box = PageBoundaries::CROP_BOX, $fallback = true)
	{
		$value = $this->getAttribute($box);

		if ($value !== null) {
			return Rectangle::byPdfArray($value, $this->parser);
		}

		if ($fallback === false) {
			return false;
		}

		switch ($box) {
			case PageBoundaries::BLEED_BOX:
			case PageBoundaries::TRIM_BOX:
			case PageBoundaries::ART_BOX:
				return $this->getBoundary(PageBoundaries::CROP_BOX, true);
			case PageBoundaries::CROP_BOX:
				return $this->getBoundary(PageBoundaries::MEDIA_BOX, true);
		}

		return false;
	}

	public function getWidthAndHeight($box = PageBoundaries::CROP_BOX, $fallback = true)
	{
		$boundary = $this->getBoundary($box, $fallback);
		if ($boundary === false) {
			return false;
		}

		$rotation = $this->getRotation();
		$interchange = ($rotation / 90) % 2;

		return [
			$interchange ? $boundary->getHeight() : $boundary->getWidth(),
			$interchange ? $boundary->getWidth() : $boundary->getHeight()
		];
	}

	public function getContentStream()
	{
		$dict = $this->getPageDictionary();
		$contents = PdfType::resolve(PdfDictionary::get($dict, 'Contents'), $this->parser);
		if ($contents instanceof PdfNull) {
			return '';
		}

		if ($contents instanceof PdfArray) {
			$result = [];
			foreach ($contents->value as $content) {
				$content = PdfType::resolve($content, $this->parser);
				if (!($content instanceof PdfStream)) {
					continue;
				}
				$result[] = $content->getUnfilteredStream();
			}

			return \implode("\n", $result);
		}

		if ($contents instanceof PdfStream) {
			return $contents->getUnfilteredStream();
		}

		throw new PdfReaderException(
			'Array or stream expected.',
			PdfReaderException::UNEXPECTED_DATA_TYPE
		);
	}

	public function getExternalLinks($box = PageBoundaries::CROP_BOX)
	{
		try {
			$dict = $this->getPageDictionary();
			$annotations = PdfType::resolve(PdfDictionary::get($dict, 'Annots'), $this->parser);
		} catch (FpdiException $e) {
			return [];
		}

		if (!$annotations instanceof PdfArray) {
			return [];
		}

		$links = [];

		foreach ($annotations->value as $entry) {
			try {
				$annotation = PdfType::resolve($entry, $this->parser);

				$value = PdfType::resolve(PdfDictionary::get($annotation, 'Subtype'), $this->parser);
				if (!$value instanceof PdfName || $value->value !== 'Link') {
					continue;
				}

				$dest = PdfType::resolve(PdfDictionary::get($annotation, 'Dest'), $this->parser);
				if (!$dest instanceof PdfNull) {
					continue;
				}

				$action = PdfType::resolve(PdfDictionary::get($annotation, 'A'), $this->parser);
				if (!$action instanceof PdfDictionary) {
					continue;
				}

				$actionType = PdfType::resolve(PdfDictionary::get($action, 'S'), $this->parser);
				if (!$actionType instanceof PdfName || $actionType->value !== 'URI') {
					continue;
				}

				$uri = PdfType::resolve(PdfDictionary::get($action, 'URI'), $this->parser);
				if ($uri instanceof PdfString) {
					$uriValue = PdfString::unescape($uri->value);
				} elseif ($uri instanceof PdfHexString) {
					$uriValue = \hex2bin($uri->value);
				} else {
					continue;
				}

				$rect = PdfType::resolve(PdfDictionary::get($annotation, 'Rect'), $this->parser);
				if (!$rect instanceof PdfArray || count($rect->value) !== 4) {
					continue;
				}

				$rect = Rectangle::byPdfArray($rect, $this->parser);
				if ($rect->getWidth() === 0 || $rect->getHeight() === 0) {
					continue;
				}

				$bbox = $this->getBoundary($box);
				$rotation = $this->getRotation();

				$gs = new GraphicsState();
				$gs->translate(-$bbox->getLlx(), -$bbox->getLly());
				$gs->rotate($bbox->getLlx(), $bbox->getLly(), -$rotation);

				switch ($rotation) {
					case 90:
						$gs->translate(-$bbox->getWidth(), 0);
						break;
					case 180:
						$gs->translate(-$bbox->getWidth(), -$bbox->getHeight());
						break;
					case 270:
						$gs->translate(0, -$bbox->getHeight());
						break;
				}

				$normalizedRect = Rectangle::byVectors(
					$gs->toUserSpace(new Vector($rect->getLlx(), $rect->getLly())),
					$gs->toUserSpace(new Vector($rect->getUrx(), $rect->getUry()))
				);

				$quadPoints = PdfType::resolve(PdfDictionary::get($annotation, 'QuadPoints'), $this->parser);
				$normalizedQuadPoints = [];
				if ($quadPoints instanceof PdfArray) {
					$quadPointsCount = count($quadPoints->value);
					if ($quadPointsCount % 8 === 0) {
						for ($i = 0; ($i + 1) < $quadPointsCount; $i += 2) {
							$x = PdfNumeric::ensure(PdfType::resolve($quadPoints->value[$i], $this->parser));
							$y = PdfNumeric::ensure(PdfType::resolve($quadPoints->value[$i + 1], $this->parser));

							$v = $gs->toUserSpace(new Vector($x->value, $y->value));
							$normalizedQuadPoints[] = $v->getX();
							$normalizedQuadPoints[] = $v->getY();
						}
					}
				}

				// we remove unsupported/unneeded values here
				unset(
					$annotation->value['P'],
					$annotation->value['NM'],
					$annotation->value['AP'],
					$annotation->value['AS'],
					$annotation->value['Type'],
					$annotation->value['Subtype'],
					$annotation->value['Rect'],
					$annotation->value['A'],
					$annotation->value['QuadPoints'],
					$annotation->value['Rotate'],
					$annotation->value['M'],
					$annotation->value['StructParent'],
					$annotation->value['OC']
				);

				// ...and flatten the PDF object to eliminate any indirect references.
				// Indirect references are a problem when writing the output in FPDF
				// because FPDF uses pre-calculated object numbers while FPDI creates
				// them at runtime.
				$annotation = PdfType::flatten($annotation, $this->parser);

				$links[] = [
					'rect' => $normalizedRect,
					'quadPoints' => $normalizedQuadPoints,
					'uri' => $uriValue,
					'pdfObject' => $annotation
				];
			} catch (FpdiException $e) {
				continue;
			}
		}

		return $links;
	}
}

abstract class PageBoundaries {
	const MEDIA_BOX = 'MediaBox';
	const CROP_BOX = 'CropBox';
	const BLEED_BOX = 'BleedBox';
	const TRIM_BOX = 'TrimBox';
	const ART_BOX = 'ArtBox';

	public static $all = array(
		self::MEDIA_BOX,
		self::CROP_BOX,
		self::BLEED_BOX,
		self::TRIM_BOX,
		self::ART_BOX
	);

	public static function isValidName($name)
	{
		return \in_array($name, self::$all, true);
	}
}

class PdfReader {
	protected $parser;
	protected $pageCount;
	protected $pages = [];

	public function __construct(PdfParser $parser)
	{
		$this->parser = $parser;
	}

	public function __destruct()
	{
		if ($this->parser !== null) {
			$this->parser->cleanUp();
		}
	}

	public function getParser()
	{
		return $this->parser;
	}

	public function getPdfVersion()
	{
		return \implode('.', $this->parser->getPdfVersion());
	}

	public function getPageCount()
	{
		if ($this->pageCount === null) {
			$catalog = $this->parser->getCatalog();

			$pages = PdfType::resolve(PdfDictionary::get($catalog, 'Pages'), $this->parser);
			$count = PdfType::resolve(PdfDictionary::get($pages, 'Count'), $this->parser);

			$this->pageCount = PdfNumeric::ensure($count)->value;
		}

		return $this->pageCount;
	}

	public function getPage($pageNumber)
	{
		if (!\is_numeric($pageNumber)) {
			throw new \InvalidArgumentException(
				'Page number needs to be a number.'
			);
		}

		if ($pageNumber < 1 || $pageNumber > $this->getPageCount()) {
			throw new \InvalidArgumentException(
				\sprintf(
					'Page number "%s" out of available page range (1 - %s)',
					$pageNumber,
					$this->getPageCount()
				)
			);
		}

		$this->readPages();

		$page = $this->pages[$pageNumber - 1];

		if ($page instanceof PdfIndirectObjectReference) {
			$readPages = function ($kids) use (&$readPages) {
				$kids = PdfArray::ensure($kids);

				/** @noinspection LoopWhichDoesNotLoopInspection */
				foreach ($kids->value as $reference) {
					$reference = PdfIndirectObjectReference::ensure($reference);
					$object = $this->parser->getIndirectObject($reference->value);
					$type = PdfDictionary::get($object->value, 'Type');

					if ($type->value === 'Pages') {
						return $readPages(PdfDictionary::get($object->value, 'Kids'));
					}

					return $object;
				}

				throw new PdfReaderException(
					'Kids array cannot be empty.',
					PdfReaderException::KIDS_EMPTY
				);
			};

			$page = $this->parser->getIndirectObject($page->value);
			$dict = PdfType::resolve($page, $this->parser);
			$type = PdfDictionary::get($dict, 'Type');

			if ($type->value === 'Pages') {
				$kids = PdfType::resolve(PdfDictionary::get($dict, 'Kids'), $this->parser);
				try {
					$page = $this->pages[$pageNumber - 1] = $readPages($kids);
				} catch (PdfReaderException $e) {
					if ($e->getCode() !== PdfReaderException::KIDS_EMPTY) {
						throw $e;
					}

					// let's reset the pages array and read all page objects
					$this->pages = [];
					$this->readPages(true);
					// @phpstan-ignore-next-line
					$page = $this->pages[$pageNumber - 1];
				}
			} else {
				$this->pages[$pageNumber - 1] = $page;
			}
		}

		return new Page($page, $this->parser);
	}

	protected function readPages($readAll = false)
	{
		if (\count($this->pages) > 0) {
			return;
		}

		$expectedPageCount = $this->getPageCount();
		$readPages = function ($kids, $count) use (&$readPages, $readAll, $expectedPageCount) {
			$kids = PdfArray::ensure($kids);
			$isLeaf = ($count->value === \count($kids->value));

			foreach ($kids->value as $reference) {
				$reference = PdfIndirectObjectReference::ensure($reference);

				if (!$readAll && $isLeaf) {
					$this->pages[] = $reference;
					continue;
				}

				$object = $this->parser->getIndirectObject($reference->value);
				$type = PdfDictionary::get($object->value, 'Type');

				if ($type->value === 'Pages') {
					$readPages(
						PdfType::resolve(PdfDictionary::get($object->value, 'Kids'), $this->parser),
						PdfType::resolve(PdfDictionary::get($object->value, 'Count'), $this->parser)
					);
				} else {
					$this->pages[] = $object;
				}

				// stop if all pages are read - faulty documents exists with additional entries with invalid data.
				if (count($this->pages) === $expectedPageCount) {
					break;
				}
			}
		};

		$catalog = $this->parser->getCatalog();
		$pages = PdfType::resolve(PdfDictionary::get($catalog, 'Pages'), $this->parser);
		$count = PdfType::resolve(PdfDictionary::get($pages, 'Count'), $this->parser);
		$kids = PdfType::resolve(PdfDictionary::get($pages, 'Kids'), $this->parser);
		$readPages($kids, $count);
	}
}


// ---

class Tokenizer {
	protected $streamReader;
	protected $stack = [];

	public function __construct(StreamReader $streamReader)
	{
		$this->streamReader = $streamReader;
	}

	public function getStreamReader()
	{
		return $this->streamReader;
	}

	public function clearStack()
	{
		$this->stack = [];
	}

	public function pushStack($token)
	{
		$this->stack[] = $token;
	}

	public function getNextToken()
	{
		$token = \array_pop($this->stack);
		if ($token !== null) {
			return $token;
		}

		if (($byte = $this->streamReader->readByte()) === false) {
			return false;
		}

		if (\in_array($byte, ["\x20", "\x0A", "\x0D", "\x0C", "\x09", "\x00"], true)) {
			if ($this->leapWhiteSpaces() === false) {
				return false;
			}
			$byte = $this->streamReader->readByte();
		}

		switch ($byte) {
			case '/':
			case '[':
			case ']':
			case '(':
			case ')':
			case '{':
			case '}':
			case '<':
			case '>':
				return $byte;
			case '%':
				$this->streamReader->readLine();
				return $this->getNextToken();
		}

		/* This way is faster than checking single bytes.
		 */
		$bufferOffset = $this->streamReader->getOffset();
		do {
			$lastBuffer = $this->streamReader->getBuffer(false);
			$pos = \strcspn(
				$lastBuffer,
				"\x00\x09\x0A\x0C\x0D\x20()<>[]{}/%",
				$bufferOffset
			);
		} while (
			// Break the loop if a delimiter or white space char is matched
			// in the current buffer or increase the buffers length
			$bufferOffset + $pos === \strlen($lastBuffer)
			&& $this->streamReader->increaseLength()
		);

		$result = \substr($lastBuffer, $bufferOffset - 1, $pos + 1);
		$this->streamReader->setOffset($bufferOffset + $pos);

		return $result;
	}

	public function leapWhiteSpaces()
	{
		do {
			if (!$this->streamReader->ensureContent()) {
				return false;
			}

			$buffer = $this->streamReader->getBuffer(false);
			$matches = \strspn($buffer, "\x20\x0A\x0C\x0D\x09\x00", $this->streamReader->getOffset());
			if ($matches > 0) {
				$this->streamReader->addOffset($matches);
			}
		} while ($this->streamReader->getOffset() >= $this->streamReader->getBufferLength());

		return true;
	}
}

// ---

class StreamReader {
	public static function createByString($content, $maxMemory = 2097152)
	{
		$h = \fopen('php://temp/maxmemory:' . ((int) $maxMemory), 'r+b');
		\fwrite($h, $content);
		\rewind($h);

		return new self($h, true);
	}

	public static function createByFile($filename)
	{
		$h = \fopen($filename, 'rb');
		return new self($h, true);
	}

	protected $closeStream;
	protected $stream;
	protected $position;
	protected $offset;
	protected $bufferLength;
	protected $totalLength;
	protected $buffer;

	public function __construct($stream, $closeStream = false)
	{
		if (!\is_resource($stream)) {
			throw new \InvalidArgumentException(
				'No stream given.'
			);
		}

		$metaData = \stream_get_meta_data($stream);
		if (!$metaData['seekable']) {
			throw new \InvalidArgumentException(
				'Given stream is not seekable!'
			);
		}

		if (fseek($stream, 0) === -1) {
			throw new \InvalidArgumentException(
				'Given stream is not seekable!'
			);
		}

		$this->stream = $stream;
		$this->closeStream = $closeStream;
		$this->reset();
	}

	public function __destruct()
	{
		$this->cleanUp();
	}

	public function cleanUp()
	{
		if ($this->closeStream && is_resource($this->stream)) {
			\fclose($this->stream);
		}
	}

	public function getBufferLength($atOffset = false)
	{
		if ($atOffset === false) {
			return $this->bufferLength;
		}

		return $this->bufferLength - $this->offset;
	}

	public function getPosition()
	{
		return $this->position;
	}

	public function getBuffer($atOffset = true)
	{
		if ($atOffset === false) {
			return $this->buffer;
		}

		$string = \substr($this->buffer, $this->offset);

		return (string) $string;
	}

	public function getByte($position = null)
	{
		$position = (int) ($position !== null ? $position : $this->offset);
		if (
			$position >= $this->bufferLength
			&& (!$this->increaseLength() || $position >= $this->bufferLength)
		) {
			return false;
		}

		return $this->buffer[$position];
	}

	public function readByte($position = null)
	{
		if ($position !== null) {
			$position = (int) $position;
			// check if needed bytes are available in the current buffer
			if (!($position >= $this->position && $position < $this->position + $this->bufferLength)) {
				$this->reset($position);
				$offset = $this->offset;
			} else {
				$offset = $position - $this->position;
			}
		} else {
			$offset = $this->offset;
		}

		if (
			$offset >= $this->bufferLength
			&& ((!$this->increaseLength()) || $offset >= $this->bufferLength)
		) {
			return false;
		}

		$this->offset = $offset + 1;
		return $this->buffer[$offset];
	}

	public function readBytes($length, $position = null)
	{
		$length = (int) $length;
		if ($position !== null) {
			// check if needed bytes are available in the current buffer
			if (!($position >= $this->position && $position < $this->position + $this->bufferLength)) {
				$this->reset($position, $length);
				$offset = $this->offset;
			} else {
				$offset = $position - $this->position;
			}
		} else {
			$offset = $this->offset;
		}

		if (
			($offset + $length) > $this->bufferLength
			&& ((!$this->increaseLength($length)) || ($offset + $length) > $this->bufferLength)
		) {
			return false;
		}

		$bytes = \substr($this->buffer, $offset, $length);
		$this->offset = $offset + $length;

		return $bytes;
	}

	public function readLine($length = 1024)
	{
		if ($this->ensureContent() === false) {
			return false;
		}

		$line = '';
		while ($this->ensureContent()) {
			$char = $this->readByte();

			if ($char === "\n") {
				break;
			}

			if ($char === "\r") {
				if ($this->getByte() === "\n") {
					$this->addOffset(1);
				}
				break;
			}

			$line .= $char;

			if (\strlen($line) >= $length) {
				break;
			}
		}

		return $line;
	}

	public function setOffset($offset)
	{
		if ($offset > $this->bufferLength || $offset < 0) {
			throw new \OutOfRangeException(
				\sprintf('Offset (%s) out of range (length: %s)', $offset, $this->bufferLength)
			);
		}

		$this->offset = (int) $offset;
	}

	public function getOffset()
	{
		return $this->offset;
	}

	public function addOffset($offset)
	{
		$this->setOffset($this->offset + $offset);
	}

	public function ensureContent()
	{
		while ($this->offset >= $this->bufferLength) {
			if (!$this->increaseLength()) {
				return false;
			}
		}
		return true;
	}

	public function getStream()
	{
		return $this->stream;
	}

	public function getTotalLength()
	{
		if ($this->totalLength === null) {
			$stat = \fstat($this->stream);
			$this->totalLength = $stat['size'];
		}

		return $this->totalLength;
	}

	public function reset($pos = 0, $length = 200)
	{
		if ($pos === null) {
			$pos = $this->position + $this->offset;
		} elseif ($pos < 0) {
			$pos = \max(0, $this->getTotalLength() + $pos);
		}

		\fseek($this->stream, $pos);

		$this->position = $pos;
		$this->offset = 0;
		if ($length > 0) {
			$this->buffer = (string) \fread($this->stream, $length);
		} else {
			$this->buffer = '';
		}
		$this->bufferLength = \strlen($this->buffer);

		// If a stream wrapper is in use it is possible that
		// length values > 8096 will be ignored, so use the
		// increaseLength()-method to correct that behavior
		if ($this->bufferLength < $length && $this->increaseLength($length - $this->bufferLength)) {
			// increaseLength parameter is $minLength, so cut to have only the required bytes in the buffer
			$this->buffer = (string) \substr($this->buffer, 0, $length);
			$this->bufferLength = \strlen($this->buffer);
		}
	}

	public function ensure($pos, $length)
	{
		if (
			$pos >= $this->position
			&& $pos < ($this->position + $this->bufferLength)
			&& ($this->position + $this->bufferLength) >= ($pos + $length)
		) {
			$this->offset = $pos - $this->position;
		} else {
			$this->reset($pos, $length);
		}
	}

	public function increaseLength($minLength = 100)
	{
		$length = \max($minLength, 100);

		if (\feof($this->stream) || $this->getTotalLength() === $this->position + $this->bufferLength) {
			return false;
		}

		$newLength = $this->bufferLength + $length;
		do {
			$this->buffer .= \fread($this->stream, $newLength - $this->bufferLength);
			$this->bufferLength = \strlen($this->buffer);
		} while (($this->bufferLength !== $newLength) && !\feof($this->stream));

		return true;
	}
}

// ---

class PdfParser {
	protected $streamReader;
	protected $tokenizer;
	protected $fileHeader;
	protected $fileHeaderOffset;
	protected $xref;
	protected $objects = [];

	public function __construct(StreamReader $streamReader)
	{
		$this->streamReader = $streamReader;
		$this->tokenizer = new Tokenizer($streamReader);
	}

	public function cleanUp()
	{
		$this->xref = null;
	}

	public function getStreamReader()
	{
		return $this->streamReader;
	}

	public function getTokenizer()
	{
		return $this->tokenizer;
	}

	protected function resolveFileHeader()
	{
		if ($this->fileHeader) {
			return $this->fileHeaderOffset;
		}

		$this->streamReader->reset(0);
		$maxIterations = 1000;
		while (true) {
			$buffer = $this->streamReader->getBuffer(false);
			$offset = \strpos($buffer, '%PDF-');
			if ($offset === false) {
				if (!$this->streamReader->increaseLength(100) || (--$maxIterations === 0)) {
					throw new PdfParserException(
						'Unable to find PDF file header.',
						PdfParserException::FILE_HEADER_NOT_FOUND
					);
				}
				continue;
			}
			break;
		}

		$this->fileHeaderOffset = $offset;
		$this->streamReader->setOffset($offset);

		$this->fileHeader = \trim($this->streamReader->readLine());
		return $this->fileHeaderOffset;
	}

	public function getCrossReference()
	{
		if ($this->xref === null) {
			$this->xref = new CrossReference($this, $this->resolveFileHeader());
		}

		return $this->xref;
	}

	public function getPdfVersion()
	{
		$this->resolveFileHeader();

		if (\preg_match('/%PDF-(\d)\.(\d)/', $this->fileHeader, $result) === 0) {
			throw new PdfParserException(
				'Unable to extract PDF version from file header.',
				PdfParserException::PDF_VERSION_NOT_FOUND
			);
		}
		list(, $major, $minor) = $result;

		$catalog = $this->getCatalog();
		if (isset($catalog->value['Version'])) {
			$versionParts = \explode(
				'.',
				PdfName::unescape(PdfType::resolve($catalog->value['Version'], $this)->value)
			);
			if (count($versionParts) === 2) {
				list($major, $minor) = $versionParts;
			}
		}

		return [(int) $major, (int) $minor];
	}

	public function getCatalog()
	{
		$trailer = $this->getCrossReference()->getTrailer();

		$catalog = PdfType::resolve(PdfDictionary::get($trailer, 'Root'), $this);

		return PdfDictionary::ensure($catalog);
	}

	public function getIndirectObject($objectNumber, $cache = false)
	{
		$objectNumber = (int) $objectNumber;
		if (isset($this->objects[$objectNumber])) {
			return $this->objects[$objectNumber];
		}

		$object = $this->getCrossReference()->getIndirectObject($objectNumber);

		if ($cache) {
			$this->objects[$objectNumber] = $object;
		}

		return $object;
	}

	public function readValue($token = null, $expectedType = null)
	{
		if ($token === null) {
			$token = $this->tokenizer->getNextToken();
		}

		if ($token === false) {
			if ($expectedType !== null) {
				throw new Type\PdfTypeException('Got unexpected token type.', Type\PdfTypeException::INVALID_DATA_TYPE);
			}
			return false;
		}

		switch ($token) {
			case '(':
				$this->ensureExpectedType($token, $expectedType);
				return $this->parsePdfString();

			case '<':
				if ($this->streamReader->getByte() === '<') {
					$this->ensureExpectedType('<<', $expectedType);
					$this->streamReader->addOffset(1);
					return $this->parsePdfDictionary();
				}

				$this->ensureExpectedType($token, $expectedType);
				return $this->parsePdfHexString();

			case '/':
				$this->ensureExpectedType($token, $expectedType);
				return $this->parsePdfName();

			case '[':
				$this->ensureExpectedType($token, $expectedType);
				return $this->parsePdfArray();

			default:
				if (\is_numeric($token)) {
					$token2 = $this->tokenizer->getNextToken();
					if ($token2 !== false) {
						if (\is_numeric($token2)) {
							$token3 = $this->tokenizer->getNextToken();
							if ($token3 === 'obj') {
								if ($expectedType !== null && $expectedType !== PdfIndirectObject::class) {
									throw new Type\PdfTypeException(
										'Got unexpected token type.',
										Type\PdfTypeException::INVALID_DATA_TYPE
									);
								}

								return $this->parsePdfIndirectObject((int) $token, (int) $token2);
							} elseif ($token3 === 'R') {
								if (
									$expectedType !== null &&
									$expectedType !== PdfIndirectObjectReference::class
								) {
									throw new Type\PdfTypeException(
										'Got unexpected token type.',
										Type\PdfTypeException::INVALID_DATA_TYPE
									);
								}

								return PdfIndirectObjectReference::create((int) $token, (int) $token2);
							} elseif ($token3 !== false) {
								$this->tokenizer->pushStack($token3);
							}
						}

						$this->tokenizer->pushStack($token2);
					}

					if ($expectedType !== null && $expectedType !== PdfNumeric::class) {
						throw new Type\PdfTypeException(
							'Got unexpected token type.',
							Type\PdfTypeException::INVALID_DATA_TYPE
						);
					}
					return PdfNumeric::create($token + 0);
				}

				if ($token === 'true' || $token === 'false') {
					$this->ensureExpectedType($token, $expectedType);
					return PdfBoolean::create($token === 'true');
				}

				if ($token === 'null') {
					$this->ensureExpectedType($token, $expectedType);
					return new PdfNull();
				}

				if ($expectedType !== null && $expectedType !== PdfToken::class) {
					throw new Type\PdfTypeException(
						'Got unexpected token type.',
						Type\PdfTypeException::INVALID_DATA_TYPE
					);
				}

				$v = new PdfToken();
				$v->value = $token;

				return $v;
		}
	}

	protected function parsePdfString()
	{
		return PdfString::parse($this->streamReader);
	}

	protected function parsePdfHexString()
	{
		return PdfHexString::parse($this->streamReader);
	}

	protected function parsePdfDictionary()
	{
		return PdfDictionary::parse($this->tokenizer, $this->streamReader, $this);
	}

	protected function parsePdfName()
	{
		return PdfName::parse($this->tokenizer, $this->streamReader);
	}

	protected function parsePdfArray()
	{
		return PdfArray::parse($this->tokenizer, $this);
	}

	protected function parsePdfIndirectObject($objectNumber, $generationNumber)
	{
		return PdfIndirectObject::parse(
			$objectNumber,
			$generationNumber,
			$this,
			$this->tokenizer,
			$this->streamReader
		);
	}

	protected function ensureExpectedType($token, $expectedType)
	{
		static $mapping = [
			'(' => PdfString::class,
			'<' => PdfHexString::class,
			'<<' => PdfDictionary::class,
			'/' => PdfName::class,
			'[' => PdfArray::class,
			'true' => PdfBoolean::class,
			'false' => PdfBoolean::class,
			'null' => PdfNull::class
		];

		if ($expectedType === null || $mapping[$token] === $expectedType) {
			return true;
		}

		throw new Type\PdfTypeException('Got unexpected token type.', Type\PdfTypeException::INVALID_DATA_TYPE);
	}
}

// ---

trait FpdiTrait {
	protected $readers = [];
	protected $createdReaders = [];
	protected $currentReaderId;
	protected $importedPages = [];
	protected $objectMap = [];
	protected $objectsToCopy = [];
	public function cleanUp($allReaders = false)
	{
		$readers = $allReaders ? array_keys($this->readers) : $this->createdReaders;
		foreach ($readers as $id) {
			$this->readers[$id]->getParser()->getStreamReader()->cleanUp();
			unset($this->readers[$id]);
		}

		$this->createdReaders = [];
	}

	protected function setMinPdfVersion($pdfVersion)
	{
		if (\version_compare($pdfVersion, $this->PDFVersion, '>')) {
			$this->PDFVersion = $pdfVersion;
		}
	}

	protected function getPdfParserInstance(StreamReader $streamReader, array $parserParams = [])
	{
		/** @noinspection PhpUndefinedClassInspection */
		if (\class_exists(FpdiPdfParser::class)) {
			/** @noinspection PhpUndefinedClassInspection */
			return new FpdiPdfParser($streamReader, $parserParams);
		}

		return new PdfParser($streamReader);
	}

	protected function getPdfReaderId($file, array $parserParams = [])
	{
		if (\is_resource($file)) {
			$id = (string) $file;
		} elseif (\is_string($file)) {
			$id = \realpath($file);
			if ($id === false) {
				$id = $file;
			}
		} elseif (\is_object($file)) {
			$id = \spl_object_hash($file);
		} else {
			throw new \InvalidArgumentException(
				\sprintf('Invalid type in $file parameter (%s)', \gettype($file))
			);
		}

		/** @noinspection OffsetOperationsInspection */
		if (isset($this->readers[$id])) {
			return $id;
		}

		if (\is_resource($file)) {
			$streamReader = new StreamReader($file);
		} elseif (\is_string($file)) {
			$streamReader = StreamReader::createByFile($file);
			$this->createdReaders[] = $id;
		} else {
			$streamReader = $file;
		}

		$reader = new PdfReader($this->getPdfParserInstance($streamReader, $parserParams));
		/** @noinspection OffsetOperationsInspection */
		$this->readers[$id] = $reader;

		return $id;
	}

	protected function getPdfReader($id)
	{
		if (isset($this->readers[$id])) {
			return $this->readers[$id];
		}

		throw new \InvalidArgumentException(
			\sprintf('No pdf reader with the given id (%s) exists.', $id)
		);
	}

	public function setSourceFile($file)
	{
		return $this->setSourceFileWithParserParams($file);
	}

	public function setSourceFileWithParserParams($file, array $parserParams = [])
	{
		$this->currentReaderId = $this->getPdfReaderId($file, $parserParams);
		$this->objectsToCopy[$this->currentReaderId] = [];

		$reader = $this->getPdfReader($this->currentReaderId);
		$this->setMinPdfVersion($reader->getPdfVersion());

		return $reader->getPageCount();
	}

	public function importPage(
		$pageNumber,
		$box = PageBoundaries::CROP_BOX,
		$groupXObject = true,
		$importExternalLinks = false
	) {
		if ($this->currentReaderId === null) {
			throw new \BadMethodCallException('No reader initiated. Call setSourceFile() first.');
		}

		$pageId = $this->currentReaderId;

		$pageNumber = (int)$pageNumber;
		$pageId .= '|' . $pageNumber . '|' . ($groupXObject ? '1' : '0') . '|' . ($importExternalLinks ? '1' : '0');

		// for backwards compatibility with FPDI 1
		$box = \ltrim($box, '/');
		if (!PageBoundaries::isValidName($box)) {
			throw new \InvalidArgumentException(
				\sprintf('Box name is invalid: "%s"', $box)
			);
		}

		$pageId .= '|' . $box;

		if (isset($this->importedPages[$pageId])) {
			return $pageId;
		}

		$reader = $this->getPdfReader($this->currentReaderId);
		$page = $reader->getPage($pageNumber);

		$bbox = $page->getBoundary($box);
		if ($bbox === false) {
			throw new PdfReaderException(
				\sprintf("Page doesn't have a boundary box (%s).", $box),
				PdfReaderException::MISSING_DATA
			);
		}

		$dict = new PdfDictionary();
		$dict->value['Type'] = PdfName::create('XObject');
		$dict->value['Subtype'] = PdfName::create('Form');
		$dict->value['FormType'] = PdfNumeric::create(1);
		$dict->value['BBox'] = $bbox->toPdfArray();

		if ($groupXObject) {
			$this->setMinPdfVersion('1.4');
			$dict->value['Group'] = PdfDictionary::create([
				'Type' => PdfName::create('Group'),
				'S' => PdfName::create('Transparency')
			]);
		}

		$resources = $page->getAttribute('Resources');
		if ($resources !== null) {
			$dict->value['Resources'] = $resources;
		}

		list($width, $height) = $page->getWidthAndHeight($box);

		$a = 1;
		$b = 0;
		$c = 0;
		$d = 1;
		$e = -$bbox->getLlx();
		$f = -$bbox->getLly();

		$rotation = $page->getRotation();

		if ($rotation !== 0) {
			$rotation *= -1;
			$angle = $rotation * M_PI / 180;
			$a = \cos($angle);
			$b = \sin($angle);
			$c = -$b;
			$d = $a;

			switch ($rotation) {
				case -90:
					$e = -$bbox->getLly();
					$f = $bbox->getUrx();
					break;
				case -180:
					$e = $bbox->getUrx();
					$f = $bbox->getUry();
					break;
				case -270:
					$e = $bbox->getUry();
					$f = -$bbox->getLlx();
					break;
			}
		}

		// we need to rotate/translate
		if ($a != 1 || $b != 0 || $c != 0 || $d != 1 || $e != 0 || $f != 0) {
			$dict->value['Matrix'] = PdfArray::create([
				PdfNumeric::create($a), PdfNumeric::create($b), PdfNumeric::create($c),
				PdfNumeric::create($d), PdfNumeric::create($e), PdfNumeric::create($f)
			]);
		}

		// try to use the existing content stream
		$pageDict = $page->getPageDictionary();

		try {
			$contentsObject = PdfType::resolve(PdfDictionary::get($pageDict, 'Contents'), $reader->getParser(), true);
			$contents =  PdfType::resolve($contentsObject, $reader->getParser());

			// just copy the stream reference if it is only a single stream
			if (
				($contentsIsStream = ($contents instanceof PdfStream))
				|| ($contents instanceof PdfArray && \count($contents->value) === 1)
			) {
				if ($contentsIsStream) {
					/**
					 * @var PdfIndirectObject $contentsObject
					 */
					$stream = $contents;
				} else {
					$stream = PdfType::resolve($contents->value[0], $reader->getParser());
				}

				$filter = PdfDictionary::get($stream->value, 'Filter');
				if (!$filter instanceof PdfNull) {
					$dict->value['Filter'] = $filter;
				}
				$length = PdfType::resolve(PdfDictionary::get($stream->value, 'Length'), $reader->getParser());
				$dict->value['Length'] = $length;
				$stream->value = $dict;
				// otherwise extract it from the array and re-compress the whole stream
			} else {
				$streamContent = $this->compress
					? \gzcompress($page->getContentStream())
					: $page->getContentStream();

				$dict->value['Length'] = PdfNumeric::create(\strlen($streamContent));
				if ($this->compress) {
					$dict->value['Filter'] = PdfName::create('FlateDecode');
				}

				$stream = PdfStream::create($dict, $streamContent);
			}
		// Catch faulty pages and use an empty content stream
		} catch (FpdiException $e) {
			$dict->value['Length'] = PdfNumeric::create(0);
			$stream = PdfStream::create($dict, '');
		}

		$externalLinks = [];
		if ($importExternalLinks) {
			$externalLinks = $page->getExternalLinks($box);
		}

		$this->importedPages[$pageId] = [
			'objectNumber' => null,
			'readerId' => $this->currentReaderId,
			'id' => 'TPL' . $this->getNextTemplateId(),
			'width' => $width / $this->k,
			'height' => $height / $this->k,
			'stream' => $stream,
			'externalLinks' => $externalLinks
		];

		return $pageId;
	}

	public function useImportedPage($pageId, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false)
	{
		if (\is_array($x)) {
			/** @noinspection OffsetOperationsInspection */
			unset($x['pageId']);
			\extract($x, EXTR_IF_EXISTS);
			/** @noinspection NotOptimalIfConditionsInspection */
			/** @phpstan-ignore function.alreadyNarrowedType  */
			if (\is_array($x)) {
				$x = 0;
			}
		}

		if (!isset($this->importedPages[$pageId])) {
			throw new \InvalidArgumentException('Imported page does not exist!');
		}

		$importedPage = $this->importedPages[$pageId];

		$originalSize = $this->getTemplateSize($pageId);
		$newSize = $this->getTemplateSize($pageId, $width, $height);
		if ($adjustPageSize) {
			$this->setPageFormat($newSize, $newSize['orientation']);
		}

		$scaleX = ($newSize['width'] / $originalSize['width']);
		$scaleY = ($newSize['height'] / $originalSize['height']);
		$xPt = $x * $this->k;
		$yPt = $y * $this->k;
		$newHeightPt = $newSize['height'] * $this->k;

		$this->_out(
			// reset standard values, translate and scale
			\sprintf(
				'q 0 J 1 w 0 j 0 G 0 g %.4F 0 0 %.4F %.4F %.4F cm /%s Do Q',
				$scaleX,
				$scaleY,
				$xPt,
				$this->hPt - $yPt - $newHeightPt,
				$importedPage['id']
			)
		);

		if (count($importedPage['externalLinks']) > 0) {
			foreach ($importedPage['externalLinks'] as $externalLink) {
				// mPDF uses also 'externalLinks' but doesn't come with a rect-value
				if (!isset($externalLink['rect'])) {
					continue;
				}

				/** @var Rectangle $rect */
				$rect = $externalLink['rect'];
				$this->Link(
					$x + $rect->getLlx() / $this->k * $scaleX,
					$y + $newSize['height'] - ($rect->getLly() + $rect->getHeight()) / $this->k * $scaleY,
					$rect->getWidth() / $this->k * $scaleX,
					$rect->getHeight()  / $this->k * $scaleY,
					$externalLink['uri']
				);

				$this->adjustLastLink($externalLink, $xPt, $scaleX, $yPt, $newHeightPt, $scaleY, $importedPage);
			}
		}

		return $newSize;
	}

	protected function adjustLastLink($externalLink, $xPt, $scaleX, $yPt, $newHeightPt, $scaleY, $importedPage)
	{
		// let's create a relation of the newly created link to the data of the external link
		$lastLink = count($this->PageLinks[$this->page]);
		$this->PageLinks[$this->page][$lastLink - 1]['importedLink'] = $externalLink;
		if (count($externalLink['quadPoints']) > 0) {
			$quadPoints = [];
			for ($i = 0, $n = count($externalLink['quadPoints']); $i < $n; $i += 2) {
				$quadPoints[] = $xPt + $externalLink['quadPoints'][$i] * $scaleX;
				$quadPoints[] = $this->hPt - $yPt - $newHeightPt + $externalLink['quadPoints'][$i + 1] * $scaleY;
			}

			$this->PageLinks[$this->page][$lastLink - 1]['quadPoints'] = $quadPoints;
		}
	}

	public function getImportedPageSize($tpl, $width = null, $height = null)
	{
		if (isset($this->importedPages[$tpl])) {
			$importedPage = $this->importedPages[$tpl];

			if ($width === null && $height === null) {
				$width = $importedPage['width'];
				$height = $importedPage['height'];
			} elseif ($width === null) {
				$width = $height * $importedPage['width'] / $importedPage['height'];
			}

			if ($height  === null) {
				$height = $width * $importedPage['height'] / $importedPage['width'];
			}

			if ($height <= 0. || $width <= 0.) {
				throw new \InvalidArgumentException('Width or height parameter needs to be larger than zero.');
			}

			return [
				'width' => $width,
				'height' => $height,
				0 => $width,
				1 => $height,
				'orientation' => $width > $height ? 'L' : 'P'
			];
		}

		return false;
	}

	protected function writePdfType(PdfType $value)
	{
		if ($value instanceof PdfNumeric) {
			if (\is_int($value->value)) {
				$this->_put($value->value . ' ', false);
			} else {
				$this->_put(\rtrim(\rtrim(\sprintf('%.5F', $value->value), '0'), '.') . ' ', false);
			}
		} elseif ($value instanceof PdfName) {
			$this->_put('/' . $value->value . ' ', false);
		} elseif ($value instanceof PdfString) {
			$this->_put('(' . $value->value . ')', false);
		} elseif ($value instanceof PdfHexString) {
			$this->_put('<' . $value->value . '>', false);
		} elseif ($value instanceof PdfBoolean) {
			$this->_put($value->value ? 'true ' : 'false ', false);
		} elseif ($value instanceof PdfArray) {
			$this->_put('[', false);
			foreach ($value->value as $entry) {
				$this->writePdfType($entry);
			}
			$this->_put(']');
		} elseif ($value instanceof PdfDictionary) {
			$this->_put('<<', false);
			foreach ($value->value as $name => $entry) {
				$this->_put('/' . $name . ' ', false);
				$this->writePdfType($entry);
			}
			$this->_put('>>');
		} elseif ($value instanceof PdfToken) {
			$this->_put($value->value);
		} elseif ($value instanceof PdfNull) {
			$this->_put('null ', false);
		} elseif ($value instanceof PdfStream) {
			$this->writePdfType($value->value);
			$this->_put('stream');
			$this->_put($value->getStream());
			$this->_put('endstream');
		} elseif ($value instanceof PdfIndirectObjectReference) {
			if (!isset($this->objectMap[$this->currentReaderId])) {
				$this->objectMap[$this->currentReaderId] = [];
			}

			if (!isset($this->objectMap[$this->currentReaderId][$value->value])) {
				$this->objectMap[$this->currentReaderId][$value->value] = ++$this->n;
				$this->objectsToCopy[$this->currentReaderId][] = $value->value;
			}

			$this->_put($this->objectMap[$this->currentReaderId][$value->value] . ' 0 R ', false);
		} elseif ($value instanceof PdfIndirectObject) {
			$n = $this->objectMap[$this->currentReaderId][$value->objectNumber];
			$this->_newobj($n);
			$this->writePdfType($value->value);

			// add newline before "endobj" for all objects in view to PDF/A conformance
			if (
				!(
					($value->value instanceof PdfArray) ||
					($value->value instanceof PdfDictionary) ||
					($value->value instanceof PdfToken) ||
					($value->value instanceof PdfStream)
				)
			) {
				$this->_put("\n", false);
			}

			$this->_put('endobj');
		}
	}
}

class Fpdi extends \TCPDF {
	use FpdiTrait {
		writePdfType as fpdiWritePdfType;
		useImportedPage as fpdiUseImportedPage;
	}

	const VERSION = '2.6.2';

	protected $templateId = 0;
	protected $currentObjectNumber;

	protected function _enddoc()
	{
		parent::_enddoc();
		$this->cleanUp();
	}

	protected function getNextTemplateId()
	{
		return $this->templateId++;
	}

	public function useTemplate($tpl, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false)
	{
		return $this->useImportedPage($tpl, $x, $y, $width, $height, $adjustPageSize);
	}

	public function useImportedPage($pageId, $x = 0, $y = 0, $width = null, $height = null, $adjustPageSize = false)
	{
		$size = $this->fpdiUseImportedPage($pageId, $x, $y, $width, $height, $adjustPageSize);
		if ($this->inxobj) {
			$importedPage = $this->importedPages[$pageId];
			$this->xobjects[$this->xobjid]['importedPages'][$importedPage['id']] = $pageId;
		}

		return $size;
	}

	public function getTemplateSize($tpl, $width = null, $height = null)
	{
		return $this->getImportedPageSize($tpl, $width, $height);
	}

	protected function _getxobjectdict()
	{
		$out = parent::_getxobjectdict();

		foreach ($this->importedPages as $pageData) {
			$out .= '/' . $pageData['id'] . ' ' . $pageData['objectNumber'] . ' 0 R ';
		}

		return $out;
	}

	protected function _putxobjects()
	{
		foreach ($this->importedPages as $key => $pageData) {
			$this->currentObjectNumber = $this->_newobj();
			$this->importedPages[$key]['objectNumber'] = $this->currentObjectNumber;
			$this->currentReaderId = $pageData['readerId'];
			$this->writePdfType($pageData['stream']);
			$this->_put('endobj');
		}

		foreach (\array_keys($this->readers) as $readerId) {
			$parser = $this->getPdfReader($readerId)->getParser();
			$this->currentReaderId = $readerId;

			while (($objectNumber = \array_pop($this->objectsToCopy[$readerId])) !== null) {
				try {
					$object = $parser->getIndirectObject($objectNumber);
				} catch (CrossReferenceException $e) {
					if ($e->getCode() === CrossReferenceException::OBJECT_NOT_FOUND) {
						$object = PdfIndirectObject::create($objectNumber, 0, new PdfNull());
					} else {
						throw $e;
					}
				}

				$this->writePdfType($object);
			}
		}

		// let's prepare resources for imported pages in templates
		foreach ($this->xobjects as $xObjectId => $data) {
			if (!isset($data['importedPages'])) {
				continue;
			}

			foreach ($data['importedPages'] as $id => $pageKey) {
				$page = $this->importedPages[$pageKey];
				$this->xobjects[$xObjectId]['xobjects'][$id] = ['n' => $page['objectNumber']];
			}
		}


		parent::_putxobjects();
		$this->currentObjectNumber = null;
	}

	protected function _put($s, $newLine = true)
	{
		if ($newLine) {
			$this->setBuffer($s . "\n");
		} else {
			$this->setBuffer($s);
		}
	}

	protected function _newobj($objid = '')
	{
		$this->_out($this->_getobj($objid));
		return $this->n;
	}

	protected function writePdfType(PdfType $value)
	{
		if (!$this->encrypted) {
			$this->fpdiWritePdfType($value);
			return;
		}

		if ($value instanceof PdfString) {
			$string = PdfString::unescape($value->value);
			$string = $this->_encrypt_data($this->currentObjectNumber, $string);
			$value->value = PdfString::escape($string);
		} elseif ($value instanceof PdfHexString) {
			$filter = new AsciiHex();
			$string = $filter->decode($value->value);
			$string = $this->_encrypt_data($this->currentObjectNumber, $string);
			$value->value = $filter->encode($string, true);
		} elseif ($value instanceof PdfStream) {
			$stream = $value->getStream();
			$stream = $this->_encrypt_data($this->currentObjectNumber, $stream);
			$dictionary = $value->value;
			$dictionary->value['Length'] = PdfNumeric::create(\strlen($stream));
			$value = PdfStream::create($dictionary, $stream);
		} elseif ($value instanceof PdfIndirectObject) {
			/**
			 * @var PdfIndirectObject $value
			 */
			$this->currentObjectNumber = $this->objectMap[$this->currentReaderId][$value->objectNumber];
		}

		$this->fpdiWritePdfType($value);
	}

	protected function adjustLastLink($externalLink, $xPt, $scaleX, $yPt, $newHeightPt, $scaleY, $importedPage)
	{
		$parser = $this->getPdfReader($importedPage['readerId'])->getParser();

		if ($this->inxobj) {
			// store parameters for later use on template
			$lastAnnotationKey = count($this->xobjects[$this->xobjid]['annotations']) - 1;
			$lastAnnotationOpt = &$this->xobjects[$this->xobjid]['annotations'][$lastAnnotationKey]['opt'];
		} else {
			$lastAnnotationKey = count($this->PageAnnots[$this->page]) - 1;
			$lastAnnotationOpt = &$this->PageAnnots[$this->page][$lastAnnotationKey]['opt'];
		}

		// ensure we have a default value - otherwise TCPDF will set it to 4 throughout
		$lastAnnotationOpt['f'] = 0;

		// values in this dictonary are all direct objects and we don't need to resolve them here again.
		$values = $externalLink['pdfObject']->value;

		foreach ($values as $key => $value) {
			try {
				switch ($key) {
					case 'BS':
						$value = PdfDictionary::ensure($value);
						$bs = [];
						if (isset($value->value['W'])) {
							$bs['w'] = PdfNumeric::ensure($value->value['W'])->value;
						}

						if (isset($value->value['S'])) {
							$bs['s'] = PdfName::ensure($value->value['S'])->value;
						}

						if (isset($value->value['D'])) {
							$d = [];
							foreach (PdfArray::ensure($value->value['D'])->value as $item) {
								$d[] = PdfNumeric::ensure($item)->value;
							}
							$bs['d'] = $d;
						}

						$lastAnnotationOpt['bs'] = $bs;
						break;

					case 'Border':
						$borderArray = PdfArray::ensure($value)->value;
						if (count($borderArray) < 3) {
							continue 2;
						}

						$border = [
							PdfNumeric::ensure($borderArray[0])->value,
							PdfNumeric::ensure($borderArray[1])->value,
							PdfNumeric::ensure($borderArray[2])->value,
						];
						if (isset($borderArray[3])) {
							$dashArray = [];
							foreach (PdfArray::ensure($borderArray[3])->value as $item) {
								$dashArray[] = PdfNumeric::ensure($item)->value;
							}
							$border[] = $dashArray;
						}

						$lastAnnotationOpt['border'] = $border;
						break;

					case 'C':
						$c = [];
						$colors = PdfArray::ensure(PdfType::resolve($value, $parser))->value;
						$m = count($colors) === 4 ? 100 : 255;
						foreach ($colors as $item) {
							$c[] = PdfNumeric::ensure($item)->value * $m;
						}
						$lastAnnotationOpt['c'] = $c;
						break;

					case 'F':
						$lastAnnotationOpt['f'] = $value->value;
						break;

					case 'BE':
						// is broken in current TCPDF version: "bc" key is checked but "bs" is used.
						break;
				}
			// let's silence invalid/not supported values
			} catch (FpdiException $e) {
				continue;
			}
		}
	}
}

// eof