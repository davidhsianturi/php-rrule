<?php

namespace RRule\Tests;

use RRule\RSet;
use RRule\RRule;
use DateTimeZone;
use ReflectionObject;
use stdClass;
use PHPUnit\Framework\TestCase;

class RSetTest extends TestCase
{
	public function testCombineRRule()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 2,
			'BYDAY' => 'TU',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addRRule(new RRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 1,
			'BYDAY' => 'TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		)));

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00')
		), $rset->getOccurrences());

		$this->assertEquals(date_create('1997-09-04 09:00'),$rset[1]);
		$this->assertEquals(array(date_create('1997-09-04 09:00')),$rset->getOccurrencesBetween('1997-09-04 00:00', '1997-09-05 00:00'));

		$this->assertTrue($rset->occursAt('1997-09-02 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-03 09:00'));

		$rset->clearCache();

		$this->assertTrue($rset->occursAt('1997-09-02 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-03 09:00'));
	}

	public function testCombineRDate()
	{
		$rset =new RSet();
		$rset->addDate(date_create('1997-09-09 09:00')); // adding out é order
		$rset->addDate('1997-09-04 09:00');
		$rset->addDate('1997-09-04 09:00'); // adding a duplicate

		$this->assertEquals(array(
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00')
		), $rset->getOccurrences(), 'occurrences are ordered and deduplicated');
	}

	public function testCombineRRuleAndDate()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 1,
			'BYDAY' => 'TU',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addDate(date_create('1997-09-04 09:00'));
		$rset->addDate(date_create('1997-09-09 09:00'));
		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00')
		), $rset->getOccurrences());

		$this->assertEquals(date_create('1997-09-04 09:00'),$rset[1]);
		$this->assertEquals(array(date_create('1997-09-04 09:00')),$rset->getOccurrencesBetween('1997-09-04 00:00', '1997-09-05 00:00'));

		$this->assertTrue($rset->occursAt('1997-09-04 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-03 09:00'));

		$rset->clearCache();

		$this->assertTrue($rset->occursAt('1997-09-04 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-03 09:00'));
	}

	/**
	 * @see https://github.com/rlanvin/php-rrule/issues/165
	 */
	public function testCombineRRuleAndRDateWithDuplicatesArrayAccess()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'WEEKLY',
			'BYDAY' => 'TU',
			'DTSTART' => date_create('2025-06-03 09:00')
		));
		$rset->addDate('2025-06-03 09:00');
		$rset->addDate('2025-06-10 09:00');
		$rset->addDate('2025-06-11 09:00');

		$occurences = [];
		for ($i = 0; $i < 5; $i++) {
			$occurences[] = $rset[$i];
		}
		$expected = [
			date_create('2025-06-03 09:00'),
			date_create('2025-06-10 09:00'),
			date_create('2025-06-11 09:00'),
			date_create('2025-06-17 09:00'),
			date_create('2025-06-24 09:00')
		];

		$this->assertEquals($expected, $occurences);
	}

	public function testRemoveDateFromRSet()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 1,
			'BYDAY' => 'TU',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addDate(date_create('1997-09-04 09:00'));
		$rset->addDate(date_create('1997-09-09 09:00'));
		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00')
		), $rset->getOccurrences());

		$rset->removeDate(date_create('1997-09-09 09:00'));

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00')
		), $rset->getOccurrences());
	}

	public function testClearDatesFromRSet()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 1,
			'BYDAY' => 'TU',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addDate(date_create('1997-09-04 09:00'));
		$rset->addDate(date_create('1997-09-09 09:00'));
		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00')
		), $rset->getOccurrences());

		$rset->clearDates();

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
		), $rset->getOccurrences());
	}

	public function testCombineRRuleAndExRule()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU,TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addExRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 3,
			'BYDAY' => 'TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-16 09:00')
		), $rset->getOccurrences());

		$this->assertEquals(date_create('1997-09-09 09:00'),$rset[1]);
		$this->assertEquals(array(date_create('1997-09-16 09:00')),$rset->getOccurrencesBetween('1997-09-16 00:00', '1997-09-17 00:00'));

		$this->assertTrue($rset->occursAt('1997-09-09 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-04 09:00'));

		$rset->clearCache();

		$this->assertTrue($rset->occursAt('1997-09-09 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-04 09:00'));
	}

	public function testCombineRRuleAndExDate()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00'); // adding out of order

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-16 09:00')
		), $rset->getOccurrences());

		$this->assertEquals(date_create('1997-09-09 09:00'),$rset[1]);
		$this->assertEquals(array(date_create('1997-09-16 09:00')),$rset->getOccurrencesBetween('1997-09-16 00:00', '1997-09-17 00:00'));

		$this->assertTrue($rset->occursAt('1997-09-02 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-04 09:00'));

		$rset->clearCache();

		$this->assertTrue($rset->occursAt('1997-09-02 09:00'));
		$this->assertFalse($rset->occursAt('1997-09-04 09:00'));
	}

	public function testRemoveExdDateFromRSet()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));

		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00'); // adding out of order

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-16 09:00')
		), $rset->getOccurrences());

		$rset->removeExdate('1997-09-11 09:00:00');

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-11 09:00'),
			date_create('1997-09-16 09:00')
		), $rset->getOccurrences());

		$rset->removeExdate('1997-09-18 09:00:00');
		$rset->removeExdate('1997-09-04 09:00:00');

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-11 09:00'),
			date_create('1997-09-16 09:00'),
			date_create('1997-09-18 09:00')
		), $rset->getOccurrences());
	}

	public function testClearExDatesFromRSet()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));

		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00'); // adding out of order

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-16 09:00')
		), $rset->getOccurrences());

		$rset->clearExDates();

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-11 09:00'),
			date_create('1997-09-16 09:00'),
			date_create('1997-09-18 09:00')
		), $rset->getOccurrences());
	}

	public function testCombineEverything()
	{
		$this->markTestIncomplete("TODO!");
	}

	public function testCombineMultipleTimezones()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'DAILY',
			'COUNT' => 2,
			'DTSTART' => date_create('2000-01-02 09:00', new DateTimeZone('Europe/Paris'))
		));
		$rset->addRRule(array(
			'FREQ' => 'DAILY',
			'COUNT' => 2,
			'DTSTART' => date_create('2000-01-02 09:00', new DateTimeZone('Europe/Helsinki'))
		));

		$this->assertEquals(array(
			date_create('2000-01-02 09:00', new DateTimeZone('Europe/Helsinki')),
			date_create('2000-01-02 09:00', new DateTimeZone('Europe/Paris')),
			date_create('2000-01-03 09:00', new DateTimeZone('Europe/Helsinki')),
			date_create('2000-01-03 09:00', new DateTimeZone('Europe/Paris'))
		), $rset->getOccurrences());
	}

///////////////////////////////////////////////////////////////////////////////
// Array access and countable interface

	public function testCountable()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$this->assertEquals(6, count($rset));

		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00');

		$this->assertEquals(3, count($rset));
	}

	public function testCannotCountInfinite()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$this->expectException('LogicException');
		count($rset);
	}

	public function testOffsetExists()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00');

		$this->assertTrue(isset($rset[0]));
		$this->assertTrue(isset($rset[1]));
		$this->assertTrue(isset($rset['1']));
		$this->assertTrue(isset($rset[2]));
		$this->assertFalse(isset($rset[3]));

		$this->assertFalse(isset($rset['foobar']));
	}

	public function testOffsetGet()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00:00')
		));
		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00');

		$this->assertEquals(date_create('1997-09-02 09:00:00'), $rset[0]);
		$this->assertEquals(date_create('1997-09-02 09:00:00'), $rset['0']);
		$this->assertEquals(date_create('1997-09-09 09:00:00'), $rset[1]);
		$this->assertEquals(date_create('1997-09-09 09:00:00'), $rset['1']);
		$this->assertEquals(date_create('1997-09-16 09:00:00'), $rset[2]);
		$this->assertEquals(null, $rset[3]);
		$this->assertEquals(null, $rset['3']);
	}

	public function testOffsetSetUnsupported()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00:00')
		));
		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00');
		$this->expectException('LogicException');
		$rset[] = 'blah';
	}

	public function testOffsetUnsetUnsupported()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00:00')
		));
		$rset->addExdate('1997-09-04 09:00:00');
		$rset->addExdate('1997-09-11 09:00:00');
		$rset->addExdate('1997-09-18 09:00:00');
		$this->expectException('LogicException');
		unset($rset[0]);
	}

	public function illegalOffsets()
	{
		return array(
			array('dtstart'),
			array('1dtstart'),
			array(array()),
			array(1.1),
			array(-1),
			array(null),
			array(new stdClass())
		);
	}

	/**
	 * @dataProvider illegalOffsets
	 */
	public function testOffsetGetInvalidArgument($offset)
	{
		$this->expectException(\InvalidArgumentException::class);
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU, TH',
			'DTSTART' => date_create('1997-09-02 09:00:00')
		));
		$rset[$offset];
	}

///////////////////////////////////////////////////////////////////////////////
// Other tests

	public function testIsInfinite()
	{
		$rset = new RSet();
		$this->assertFalse($rset->isInfinite());
		$this->assertTrue($rset->isFinite());

		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 10
		));
		$this->assertFalse($rset->isInfinite());
		$this->assertTrue($rset->isFinite());

		$rset->addRRule(array(
			'FREQ' => 'YEARLY'
		));
		$this->assertTrue($rset->isInfinite());
		$this->assertFalse($rset->isFinite());
	}

	public function testModifyResetCache()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU,TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-04 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-11 09:00'),
			date_create('1997-09-16 09:00'),
			date_create('1997-09-18 09:00')
		), $rset->getOccurrences());

		$r = new ReflectionObject($rset);
		$cache = $r->getProperty('cache');
		$cache->setAccessible('true');
		$this->assertNotEmpty($cache->getValue($rset), 'Cache is not empty');

		$rset->addExRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 3,
			'BYDAY' => 'TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$this->assertEmpty($cache->getValue($rset), 'Cache has been emptied by addExRule');

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-16 09:00')
		), $rset->getOccurrences(), 'Iteration works');
	}

	public function testPartialCache()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 6,
			'BYDAY' => 'TU,TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addExRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 3,
			'BYDAY' => 'TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));

		foreach ($rset as $occurrence) {
			$this->assertEquals(date_create('1997-09-02 09:00'), $occurrence);
			break;
		}

		$r = new ReflectionObject($rset);
		$cache = $r->getProperty('cache');
		$cache->setAccessible('true');
		$this->assertNotEmpty($cache->getValue($rset), 'Cache is not empty (partially filled)');

		$this->assertEquals(date_create('1997-09-02 09:00'), $rset[0], 'Partial cache is returned');
		$this->assertEquals(date_create('1997-09-09 09:00'), $rset[1], 'Next occurrence is calculated correctly');

		$this->assertEquals(array(
			date_create('1997-09-02 09:00'),
			date_create('1997-09-09 09:00'),
			date_create('1997-09-16 09:00')
		), $rset->getOccurrences(), 'Iteration works');
	}

	public function testRSetInRset()
	{
		$rset = new RSet();
		$rset->addRRule($rset);
		$rset->addDate('2016-03-21');

		$this->assertEquals(
			array(date_create('2016-03-21')),
			$rset->getOccurrences(),
			'Adding the RSet into itself does not explode'
		);

		$sub_rset = new RSet();
		$sub_rset->addDate('2016-03-21 10:00');
		$sub_rset->addDate('2016-03-21 11:00');

		$rset = new RSet();
		$rset->addRRule($sub_rset);

		$this->assertEquals(array(
			date_create('2016-03-21 10:00'),
			date_create('2016-03-21 11:00')
		), $rset->getOccurrences());

		$rset->addExDate('2016-03-21 11:00');
		$this->assertEquals(array(
			date_create('2016-03-21 10:00')
		), $rset->getOccurrences());
	}

	public function testGetter()
	{
		$rset = new RSet();
		$rset->addRRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 2,
			'BYDAY' => 'TU',
			'DTSTART' => date_create('1997-09-02 09:00')
		));
		$rset->addRRule(new RRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 1,
			'BYDAY' => 'TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		)));
		$rset->addDate(date_create('1997-09-04 09:00'));
		$rset->addDate(date_create('1997-09-05 09:00'));
		$rset->addDate(date_create('1997-09-06 09:00'));
		$rset->addExRule(array(
			'FREQ' => 'YEARLY',
			'COUNT' => 3,
			'BYDAY' => 'TH',
			'DTSTART' => date_create('1997-09-02 09:00')
		));

		$this->assertIsArray($rset->getRRules());
		$this->assertCount(2, $rset->getRRules());
		$this->assertIsArray($rset->getExRules());
		$this->assertCount(1, $rset->getExRules());
		$this->assertIsArray($rset->getDates());
		$this->assertCount(3, $rset->getDates());
		$this->assertIsArray($rset->getExDates());
		$this->assertCount(0, $rset->getExDates());
	}

///////////////////////////////////////////////////////////////////////////////
// GetOccurrences

	public function testGetOccurrences()
	{
		$rset = new RSet();
		$rset->addRRule(new RRule(array(
			'FREQ' => 'DAILY',
			'DTSTART' => '2017-01-01'
		)));

		$this->assertCount(1, $rset->getOccurrences(1));
		$this->assertEquals(array(date_create('2017-01-01')), $rset->getOccurrences(1));
		$this->assertCount(5, $rset->getOccurrences(5));
		$this->assertEquals(array(
			date_create('2017-01-01'),date_create('2017-01-02'),date_create('2017-01-03'),
			date_create('2017-01-04'),date_create('2017-01-05')
		), $rset->getOccurrences(5));
	}

	public function testGetOccurrencesThrowsLogicException()
	{
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage("Cannot get all occurrences of an infinite recurrence set.");
		$rset = new RSet();
		$rset->addRRule(new RRule(array(
			'FREQ' => 'DAILY',
			'DTSTART' => '2017-01-01'
		)));
		$rset->getOccurrences();
	}
	public function occurrencesBetween()
	{
		return [
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-01', null, 1, [date_create('2017-01-01')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-02-01', '2017-12-31', 1, [date_create('2017-02-01')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-01', null, 5, [
				date_create('2017-01-01'),
				date_create('2017-01-02'),
				date_create('2017-01-03'),
				date_create('2017-01-04'),
				date_create('2017-01-05'),
			]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-01', '2017-01-05', null, [
				date_create('2017-01-01'),
				date_create('2017-01-02'),
				date_create('2017-01-03'),
				date_create('2017-01-04'),
				date_create('2017-01-05'),
			]],
		];
	}

	/**
	 * @dataProvider occurrencesBetween
	 */
	public function testGetOccurrencesBetween(RSet $rset, $begin, $end, $limit, $expected)
	{
		$this->assertEquals($expected, $rset->getOccurrencesBetween($begin, $end, $limit));
	}


	public function testGetOccurrencesBetweenThrowsLogicException()
	{
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage("Cannot get all occurrences of an infinite recurrence rule.");

		$rset = new RSet();
		$rset->addRRule(new RRule(array(
			'FREQ' => 'DAILY',
			'DTSTART' => '2017-01-01'
		)));
		$rset->getOccurrencesBetween('2017-01-01', null);
	}

	public function occurrencesAfter()
	{
		return [
			[
				(new RSet())
					->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2")
					->addRRule("DTSTART:20170102\nRRULE:FREQ=DAILY;INTERVAL=2")
				, '2017-02-01', false, 2, [date_create('2017-02-02'),date_create('2017-02-03')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-02-01', true, 2, [date_create('2017-02-01'),date_create('2017-02-02')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-02', true, 2, [date_create('2017-01-03'),date_create('2017-01-05')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-02', false, 2, [date_create('2017-01-03'),date_create('2017-01-05')]],
		];
	}

	/**
	 * @dataProvider occurrencesAfter
	 */
	public function testGetOccurrencesAfter(RSet $rset, $date, $inclusive, $limit, $expected)
	{
		$occurrences = $rset->getOccurrencesAfter($date, $inclusive, $limit);
		$this->assertEquals($expected, $occurrences);
	}

	public function occurrencesBefore()
	{
		return [
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-02-01', true, 2, [date_create('2017-01-31'),date_create('2017-02-01')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-02-01', false, 2, [date_create('2017-01-30'),date_create('2017-01-31')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-02', false, null, [date_create('2017-01-01')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-02', false, 5, [date_create('2017-01-01')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-04', true, 2, [date_create('2017-01-01'),date_create('2017-01-03')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-04', false, 2, [date_create('2017-01-01'),date_create('2017-01-03')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-02', false, null, [date_create('2017-01-01')]],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-02', false, 5, [date_create('2017-01-01')]],
		];
	}
	/**
	 * @dataProvider occurrencesBefore
	 */
	public function testGetOccurrencesBefore(RSet $rset, $date, $inclusive, $limit, $expected)
	{
		$occurrences = $rset->getOccurrencesBefore($date, $inclusive, $limit);
		$this->assertEquals($expected, $occurrences);
	}

	public function nthOccurrences()
	{
		return [
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-01', 0, date_create('2017-01-01')],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-01', 1, date_create('2017-01-02')],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-01', 2, date_create('2017-01-05')],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-02', 0, null],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-01', -1, null],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-10', -1, date_create('2017-01-09')],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY"), '2017-01-10', -2, date_create('2017-01-08')],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-11', -2, date_create('2017-01-07')],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;INTERVAL=2"), '2017-01-10', -2, date_create('2017-01-07')],

			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;COUNT=2"), '2017-01-01', 3, null],
			[(new RSet())->addRRule("DTSTART:20170101\nRRULE:FREQ=DAILY;UNTIL=20170102"), '2017-01-01', 3, null],

		];
	}

	/**
	 * @dataProvider nthOccurrences
	 */
	public function testGetNthOccurrenceFrom(RSet $rset, $date, $index, $result)
	{
		$occurrence = $rset->getNthOccurrenceFrom($date, $index);
		$this->assertEquals($result, $occurrence);
	}

///////////////////////////////////////////////////////////////////////////////
// RFC Strings

	public function rfcStrings()
	{
		return array(
			array(
				"DTSTART;TZID=America/New_York:19970901T090000
				RRULE:FREQ=DAILY;COUNT=3
				EXDATE;TZID=America/New_York:19970902T090000",
				array(
					date_create('1997-09-01 09:00:00', new \DateTimeZone('America/New_York')),
					date_create('1997-09-03 09:00:00', new \DateTimeZone('America/New_York'))
				)
			),
			array(
				"DTSTART;TZID=America/New_York:19970901T090000
				RRULE:FREQ=DAILY;COUNT=3
				EXRULE:FREQ=DAILY;INTERVAL=2;COUNT=1
				EXDATE;TZID=America/New_York:19970903T090000
				RDATE;TZID=America/New_York:19970904T090000",
				array(
					date_create('1997-09-02 09:00:00', new \DateTimeZone('America/New_York')),
					date_create('1997-09-04 09:00:00', new \DateTimeZone('America/New_York'))
				)
			),
			array(
				"EXDATE;VALUE=DATE-TIME:20171227T200000Z
				RRULE:FREQ=MONTHLY;WKST=MO;BYDAY=-1WE;UNTIL=20180131T200000Z
				DTSTART:20171129T200000Z",
				array(
					date_create('2017-11-29 20:00:00', new \DateTimeZone('GMT')),
					date_create('2018-01-31 20:00:00', new \DateTimeZone('GMT'))
				)
			),
		);
	}

	/**
	 * @dataProvider rfcStrings
	 */
	public function testParseRfcString($string, $occurrences)
	{
		$object = new RSet($string);
		$this->assertEquals($occurrences, $object->getOccurrences());
	}

	public function testParseRfcStringWithDtStart()
	{
		$rset = new RSet(
			"RRULE:FREQ=DAILY;COUNT=3\nEXRULE:FREQ=DAILY;INTERVAL=2;COUNT=1"
		);
		$this->assertEquals(array(
			// get rid of microseconds for PHP 7.1+
			date_create(date_create('+1day')->format('Y-m-d H:i:s')),
			date_create(date_create('+2day')->format('Y-m-d H:i:s'))
		), $rset->getOccurrences());

		$rset = new RSet(
			"RRULE:FREQ=DAILY;COUNT=3\nEXRULE:FREQ=DAILY;INTERVAL=2;COUNT=1",
			'2017-01-01'
		);
		$this->assertEquals(array(
			date_create('2017-01-02'),
			date_create('2017-01-03')
		), $rset->getOccurrences());

		$rset = new RSet(
			"RRULE:FREQ=DAILY;COUNT=3\nEXRULE:FREQ=DAILY;INTERVAL=2;COUNT=1",
			date_create('2017-01-01')
		);
		$this->assertEquals(array(
			date_create('2017-01-02'),
			date_create('2017-01-03')
		), $rset->getOccurrences());
	}

	public function testParseRfcStringWithMultipleDtStart()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage("Failed to parse RFC string, multiple DTSTART found");
		$rset = new RSet(
			"DTSTART:DTSTART;TZID=America/New_York:19970901T090000\nRRULE:FREQ=DAILY;COUNT=3\nEXRULE:FREQ=DAILY;INTERVAL=2;COUNT=1",
			date_create('2017-01-01')
		);
	}

	public function quirkyRfcStrings()
	{
		return array(
			array(
				'RRULE:FREQ=MONTHLY;DTSTART=20170201T010000Z;UNTIL=20170228T030000Z;BYDAY=TU
				RDATE:20170222T010000Z
				EXDATE:20170221T010000Z',
				array(
					date_create('20170207T010000Z'),
					date_create('20170214T010000Z'),
					date_create('20170222T010000Z'),
					date_create('20170228T010000Z')
				)
			)
		);
	}

	/**
	 * @dataProvider quirkyRfcStrings
	 */
	public function testParseQuirkyRfcStringNotice($string, $occurrences)
	{
		try {
			$object = new RSet($string);
			$this->fail("Expected an notice, didn't get one");
		} catch (\PHPUnit\Framework\Error\Notice $e) {
			$this->assertStringContainsString("This string is not compliant with the RFC (DTSTART cannot be part of RRULE).", $e->getMessage());
		}
	}

	/**
	 * @dataProvider quirkyRfcStrings
	 */
	public function testParseQuirkyRfcString($string, $occurrences)
	{
		$object = @ new RSet($string);
		$this->assertEquals($occurrences, $object->getOccurrences());
	}
}