<?php
# WARNING: This file is publically viewable on the web. Do not put private data here.

# This file hold the CirrusSearch configuration which is common to all realms,
# ie settings should apply to both the production cluster and the beta
# cluster.
# If you ever want to stick there an IP address, you should use the per realm
# specific files CirrusSearch-labs.php and CirrusSearch-production.php

# See: https://wikitech.wikimedia.org/wiki/Search
#
# Contact Wikimedia operations or platform engineering for more details.

$wgSearchType = 'CirrusSearch';

if ( $wmgUseClusterJobqueue ) {
	# The secondary update job has a delay of a few seconds to make sure that Elasticsearch
	# has completed a refresh cycle between when the data that the job needs is added and
	# when the job is run.
	$wgJobTypeConf['cirrusSearchIncomingLinkCount'] = array( 'checkDelay' => true ) +
		$wgJobTypeConf['default'];
}

# Set up the the default cluster to send queries to,
# and the list of clusters to write to.
if ( $wmgCirrusSearchDefaultCluster === 'local' ) {
	$wgCirrusSearchDefaultCluster = $wmfDatacenter;
} else {
	$wgCirrusSearchDefaultCluster = $wmgCirrusSearchDefaultCluster;
}
$wgCirrusSearchWriteClusters = $wmgCirrusSearchWriteClusters;

# Enable user testing
$wgCirrusSearchUserTesting = $wmgCirrusSearchUserTesting;

# Turn off leading wildcard matches, they are a very slow and inefficient query
$wgCirrusSearchAllowLeadingWildcard = false;

# Turn off the more accurate but slower search mode.  It is most helpful when you
# have many small shards.  We don't do that in production and we could use the speed.
$wgCirrusSearchMoreAccurateScoringMode = false;

# Raise the refresh interval to save some CPU at the cost of being slightly less realtime.
$wgCirrusSearchRefreshInterval = 30;

# Limit the number of states generated by wildcard queries (500 will allow about 20 wildcards)
$wgCirrusSearchQueryStringMaxDeterminizedStates = 500;

# Lower the regex timeouts - the defaults are too high in an environment with reverse proxies.
$wgCirrusSearchSearchShardTimeout[ 'regex' ] = '40s';
$wgCirrusSearchClientSideSearchTimeout[ 'regex' ] = 80;

# Set the backoff for Cirrus' job that reacts to template changes - slow and steady
# will help prevent spikes in Elasticsearch load.
// $wgJobBackoffThrottling['cirrusSearchLinksUpdate'] = 5;  -- disabled, Ori 3-Dec-2015
# Also engage a delay for the Cirrus job that counts incoming links to pages when
# pages are newly linked or unlinked.  Too many link count queries at once could flood
# Elasticsearch.
// $wgJobBackoffThrottling['cirrusSearchIncomingLinkCount'] = 1; -- disabled, Ori 3-Dec-2015

# Ban the hebrew plugin, it is unstable
$wgCirrusSearchBannedPlugins[] = 'elasticsearch-analysis-hebrew';

# Build and use an ngram index for faster regex matching
$wgCirrusSearchWikimediaExtraPlugin = array(
	'regex' => array(
		'build',
		'use',
	),
	'super_detect_noop' => true,
	'id_hash_mod_filter' => true,
);

# Enable the "experimental" highlighter on all wikis
$wgCirrusSearchUseExperimentalHighlighter = true;
$wgCirrusSearchOptimizeIndexForExperimentalHighlighter = true;

# Setup the feedback link on Special:Search if enabled
$wgCirrusSearchFeedbackLink = $wmgCirrusSearchFeedbackLink;

# Settings customized per index.
$wgCirrusSearchShardCount = $wmgCirrusSearchShardCount;
$wgCirrusSearchReplicas = $wmgCirrusSearchReplicas;
$wgCirrusSearchMaxShardsPerNode = $wmgCirrusSearchMaxShardsPerNode;
$wgCirrusSearchPreferRecentDefaultDecayPortion = $wmgCirrusSearchPreferRecentDefaultDecayPortion;
$wgCirrusSearchBoostLinks = $wmgCirrusSearchBoostLinks;
$wgCirrusSearchWeights = array_merge( $wgCirrusSearchWeights, $wmgCirrusSearchWeightsOverrides );
$wgCirrusSearchPowerSpecialRandom = $wmgCirrusSearchPowerSpecialRandom;
$wgCirrusSearchAllFields = $wmgCirrusSearchAllFields;
$wgCirrusSearchNamespaceWeights = $wmgCirrusSearchNamespaceWeightOverrides +
	$wgCirrusSearchNamespaceWeights;

// We had an incident of filling up the entire clusters redis instances after
// 6 hours, half of that seems reasonable.
$wgCirrusSearchDropDelayedJobsAfter = 60 * 60 * 3;

// Enable cache warming for wikis with more than one shard.  Cache warming is good
// for smoothing out I/O spikes caused by merges at the cost of potentially polluting
// the cache by adding things that won't be used.

// Wikis with more then one shard or with multi-cluster configuration is a
// decent way of saying "wikis we expect will get some search traffic every
// few seconds".  In this commonet the term "cache" refers to all kinds of
// caches: the linux disk cache, Elasticsearch's filter cache, whatever.
if ( isset( $wgCirrusSearchShardCount['eqiad'] ) ) {
	$wgCirrusSearchMainPageCacheWarmer = true;
} else {
	$wgCirrusSearchMainPageCacheWarmer = ( $wgCirrusSearchShardCount['content'] > 1 );
}

// Enable concurrent search limits for specified abusive networks
$wgCirrusSearchForcePerUserPoolCounter = $wmgCirrusSearchForcePerUserPoolCounter;

// Commons is special
if ( $wgDBname == 'commonswiki' ) {
	$wgCirrusSearchNamespaceMappings[ NS_FILE ] = 'file';
	$wgCirrusSearchReplicaCount['file'] = 2;
} elseif ( $wgDBname == 'officewiki' || $wgDBname == 'foundationwiki' ) {
	// T94856 - makes searching difficult for locally uploaded files
	// T76957 - doesn't make sense to have Commons files on foundationwiki search
} else { // So is everyone else, for using commons
	$wgCirrusSearchExtraIndexes[ NS_FILE ] = array( 'commonswiki_file' );
}

// Configuration for initial test deployment of inline interwiki search via
// language detection on the search terms. With EnableAltLanguage set to false
// this is only available with a special query string (cirrusAltLanguage=yes)
$wgCirrusSearchEnableAltLanguage = $wmgCirrusSearchEnableAltLanguage;
$wgCirrusSearchInterwikiProv = 'iwsw1';

$wgCirrusSearchWikiToNameMap = $wmgCirrusSearchWikiToNameMap;
$wgCirrusSearchLanguageToWikiMap = $wmgCirrusSearchLanguageToWikiMap;

// will be overridden by UserTesting triggers, but we need to set the default.
$wgCirrusSearchTextcatLanguages = array();
$wgCirrusSearchTextcatModel = "$IP/vendor/wikimedia/textcat/LM-query";

$wgHooks['CirrusSearchMappingConfig'][] = function( array &$config, $mappingConfigBuilder ) {
	$config['page']['properties']['popularity_score'] = array(
		'type' => 'double',
	);
};

// Set the scoring method
$wgCirrusSearchCompletionDefaultScore = 'popqual';

// PoolCounter needs to be adjusted to account for additional latency when default search
// is pointed at a remote datacenter. Currently this makes the assumption that it will either
// be eqiad or codfw which have ~40ms latency between them. Multiples are chosen using
// (p75 + cross dc latency)/p75
if ( $wgCirrusSearchDefaultCluster !== $wmfDatacenter ) {
	// prefix has p75 of ~30ms
	if ( isset( $wgPoolCounterConf[ 'CirrusSearch-Prefix' ] ) ) {
		$wgPoolCounterConf['CirrusSearch-Prefix']['workers'] *= 2;
	}
	// namespace has a p75 of ~15ms
	if ( isset( $wgPoolCounterConf['CirrusSearch-NamespaceLookup' ] ) ) {
		$wgPoolCounterConf['CirrusSearch-NamespaceLookup']['workers'] *= 3;
	}
	// completion has p75 of ~30ms
	if ( isset( $wgPoolCounterConf['CirrusSearch-Completion'] ) ) {
		$wgPoolCounterConf['CirrusSearch-Completion']['workers'] *= 2;
	}
}

// Enable completion suggester
$wgCirrusSearchUseCompletionSuggester = $wmgCirrusSearchUseCompletionSuggester;

// Configure ICU Folding
$wgCirrusSearchUseIcuFolding = $wmgCirrusSearchUseIcuFolding;

# Load per realm specific configuration, either:
# - CirrusSearch-labs.php
# - CirrusSearch-production.php
#
require "{$wmfConfigDir}/CirrusSearch-{$wmfRealm}.php";
