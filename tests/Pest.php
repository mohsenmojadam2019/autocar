<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Unit tests still boot Laravel services/config, but do not reset the database unless explicitly requested.
pest()->extend(TestCase::class)->in('Unit');

// Feature tests exercise the full schema against a clean database for every test.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
