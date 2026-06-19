<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet
	version="1.0"
	xmlns="http://www.w3.org/1999/xhtml"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:luna="http://lunarsystem.org/ontology#"
	xmlns:dcterms="http://purl.org/dc/terms/"
	xmlns:foaf="http://xmlns.com/foaf/0.1/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
	xmlns:xsd="http://www.w3.org/2001/XMLSchema#"
	xmlns:owl="http://www.w3.org/2002/07/owl#"
	xmlns:dc="http://purl.org/dc/elements/1.1/">

	<xsl:output
		encoding="utf-8"
		method="xml"
		indent="yes"
		omit-xml-declaration="no"
		doctype-public="-//W3C//DTD XHTML 1.0 Strict//EN"
		doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd" />

	<xsl:variable name="cleanurls"><xsl:value-of select="/rdf:RDF/luna:data[luna:lid = 'clean_urls']/luna:value"/></xsl:variable>
	<xsl:variable name="site_uri"><xsl:value-of select="/rdf:RDF/luna:data[luna:lid = 'site_uri']/luna:value/@rdf:resource"/></xsl:variable>
	<xsl:variable name="lang"><xsl:value-of select="/rdf:RDF/luna:data[luna:lid = 'lang']/luna:value"/></xsl:variable>
	<xsl:variable name="masternodelid"><xsl:value-of select="/rdf:RDF/luna:data[luna:lid = 'lid']/luna:value"/></xsl:variable>
	<xsl:variable name="masternodenid"><xsl:value-of select="/rdf:RDF/luna:page[luna:lid = $masternodelid]/luna:nid"/></xsl:variable>
	<xsl:variable name="masternodeurl">
		<xsl:call-template name="link"><xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/luna:alias"/></xsl:call-template>
	</xsl:variable>
	<xsl:variable name="site_relative_url"><xsl:value-of select="/rdf:RDF/luna:data[luna:lid = 'site_relative_url']/luna:value"/></xsl:variable>
	<xsl:variable name="pageurl"><xsl:value-of select="$masternodeurl"/></xsl:variable>
	<xsl:variable name="mod_nid"><xsl:value-of select="/rdf:RDF/luna:mod[luna:lid = $mod_lid]/luna:nid"/></xsl:variable>
	<xsl:variable name="guest">
		<xsl:choose>
			<xsl:when test="/rdf:RDF/foaf:Person[luna:is_current = '1']/luna:is_guest = '1'">1</xsl:when>
			<xsl:otherwise>0</xsl:otherwise>
		</xsl:choose>
	</xsl:variable>

	<xsl:include href="./luna.common.html.xsl"/>

	<xsl:template match="/rdf:RDF">
		<html>
			<xsl:attribute name="lang"><xsl:value-of select="$lang"/></xsl:attribute>
			<xsl:attribute name="xml:lang"><xsl:value-of select="$lang"/></xsl:attribute>
			<head>
				<xsl:attribute name="profile"><xsl:text>http://ns.inria.fr/grddl/rdfa/</xsl:text></xsl:attribute>
				<meta name="viewport" content="width=device-width, initial-scale=1"/>
				<title>
					<xsl:value-of select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/rdfs:label"/>
					<xsl:text> · </xsl:text>
					<xsl:value-of select="luna:data[luna:lid = 'sitename']/luna:value"/>
				</title>
				<meta http-equiv="Content-Language">
					<xsl:attribute name="content"><xsl:value-of select="$lang"/></xsl:attribute>
				</meta>
				<link rel="schema.DC" href="http://purl.org/dc/elements/1.1/" />
				<meta name="DC.Title">
					<xsl:attribute name="content">
						<xsl:value-of select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/rdfs:label"/>
						<xsl:text> · </xsl:text>
						<xsl:value-of select="luna:data[luna:lid = 'sitename']/luna:value"/>
					</xsl:attribute>
					<xsl:attribute name="lang"><xsl:value-of select="$lang"/></xsl:attribute>
					<xsl:attribute name="xml:lang"><xsl:value-of select="$lang"/></xsl:attribute>
				</meta>
				<meta name="DC.Type" content="text" />
				<meta name="DC.Format" content="text/html" />
				<meta name="DC.Identifier">
					<xsl:attribute name="content"><xsl:value-of select="$site_uri"/></xsl:attribute>
				</meta>
				<meta name="DC.Language" scheme="RFC3066">
					<xsl:attribute name="content"><xsl:value-of select="$lang"/></xsl:attribute>
				</meta>
				<meta name="DC.Date" scheme="W3CDTF">
					<xsl:attribute name="content"><xsl:value-of select="/rdf:RDF/luna:data[luna:nid = $masternodenid]/dc:date"/></xsl:attribute>
				</meta>
				<link rel="stylesheet" type="text/css" media="all">
					<xsl:attribute name="href">
						<xsl:value-of select="$site_relative_url"/><xsl:text>css/styles.css</xsl:text>
					</xsl:attribute>
				</link>
				<link rel="alternate" type="application/rdf+xml">
					<xsl:attribute name="href">
						<xsl:call-template name="link">
							<xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/luna:alias"/>
							<xsl:with-param name="options">
								<xsl:text>output=xml</xsl:text>
							</xsl:with-param>
						</xsl:call-template>
					</xsl:attribute>
				</link>
				<script type="text/javascript">
					<xsl:comment>
<xsl:text>
var basehref='</xsl:text><xsl:value-of select="$site_uri"/><xsl:text>'; var pagenid='</xsl:text><xsl:value-of select="$masternodenid"/> <xsl:text>'; var pagelid='</xsl:text><xsl:value-of select="$masternodelid"/> <xsl:text>'; var lang='</xsl:text><xsl:value-of select="$lang"/><xsl:text>';
</xsl:text>
<xsl:text>
var ckeditorconfig = 0;
</xsl:text>
					</xsl:comment>
				</script>
				<script type="text/javascript">
					<xsl:attribute name="src">https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js</xsl:attribute>
					<xsl:attribute name="integrity">sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==</xsl:attribute>
					<xsl:attribute name="crossorigin">anonymous</xsl:attribute>
					<xsl:attribute name="referrerpolicy">no-referrer</xsl:attribute>
				</script>
				<script type="text/javascript">
					<xsl:attribute name="src">
						<xsl:value-of select="$site_relative_url"/><xsl:text>js/luna.js</xsl:text>
					</xsl:attribute>
				</script>
			</head>
			<body class="main">
				<div id="Page">
					<xsl:attribute name="class"><xsl:value-of select="$masternodelid"/></xsl:attribute>
					<div id="Top">
						<div class="box">
							<h1>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link"><xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:lid = 'root']/luna:alias"/></xsl:call-template>
									</xsl:attribute>
									<span id="SiteTitle"><xsl:value-of select="luna:data[luna:lid = 'sitename']/luna:value"/></span>
								</a>
							</h1>
						</div>
						<div class="box">
							<h1>
								<a>
									<xsl:attribute name="href">
										<xsl:value-of select="$pageurl"/>
									</xsl:attribute>
									<xsl:value-of select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/rdfs:label"/>
								</a>
							</h1>
						</div>
					</div>
					<hr/>
					<div id="Content">
						<xsl:if test="/rdf:RDF/luna:message">
							<div id="Messages" class="box"><xsl:apply-templates select="/rdf:RDF/luna:message"/></div>
						</xsl:if>
						<div id="Content-main"><xsl:call-template name="page"/></div>
						<div id="Bottom" class="box">
							<div class="box-content">
								<p>
									<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'You are here: ']/luna:value"/>
									<a>
										<xsl:attribute name="href"><xsl:value-of select="$pageurl"/></xsl:attribute>
										<xsl:value-of select="$pageurl"/>
									</a>
									<br/>
									<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'You are logged in as']/luna:value"/><xsl:text> </xsl:text>
									<strong><xsl:value-of select="/rdf:RDF/foaf:Person[luna:is_current = '1']/foaf:name"/></strong>
									<xsl:text> [</xsl:text>
									<a>
										<xsl:choose>
											<xsl:when test="$guest = '1'">
												<xsl:attribute name="href">
													<xsl:call-template name="link"><xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:lid = 'login']/luna:alias"/></xsl:call-template>
												</xsl:attribute>
												<xsl:value-of select="/rdf:RDF/luna:page[luna:lid = 'login']/rdfs:label"/>
											</xsl:when>
											<xsl:otherwise>
												<xsl:attribute name="href">
													<xsl:call-template name="link"><xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:lid = 'logout']/luna:alias"/></xsl:call-template>
												</xsl:attribute>
												<xsl:value-of select="/rdf:RDF/luna:page[luna:lid = 'logout']/rdfs:label"/>
											</xsl:otherwise>
										</xsl:choose>
									</a>
									<xsl:text>] </xsl:text>
									<br/>
									<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Site powered by']/luna:value"/><xsl:text> </xsl:text><a href="http://lunarsystem.org">lunarSystem</a><xsl:text> </xsl:text><xsl:value-of select="luna:data[luna:lid = 'lunaversion']/luna:value"/>
								</p>
							</div>
						</div>
					</div>
					<hr/>
					<div id="Nav">
						<h1 class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Navigation']/luna:value"/></h1>
						<!-- <xsl:if test="not($guest = '1')">  -->
							<!-- <xsl:call-template name="search"/>  -->
							<xsl:call-template name="sitemap"/>
						<!-- </xsl:if>  -->
						<div id="Options">
							<xsl:if test="count(/rdf:RDF/luna:lang) &gt; 1">
								<h2 class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'langs']/luna:value"/></h2>
								<ul class="langs">
									<xsl:for-each select="/rdf:RDF/luna:lang">
										<li>
											<a>
												<xsl:if test="luna:selected = '1'">
													<xsl:attribute name="class"><xsl:text>on</xsl:text></xsl:attribute>
												</xsl:if>
												<xsl:attribute name="href"><xsl:value-of select="luna:link"/></xsl:attribute>
												<xsl:attribute name="title"><xsl:value-of select="luna:value"/></xsl:attribute>
												<xsl:value-of select="luna:value"/>
											</a>
										</li>
									</xsl:for-each>
								</ul>
							</xsl:if>
							<!-- <xsl:if test="not($guest = '1')">  -->
								<h2 class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Output formats']/luna:value"/></h2>
								<ul class="outputs">
									<xsl:for-each select="/rdf:RDF/luna:output-format">
										<li>
											<a>
												<xsl:if test="luna:selected = '1'">
													<xsl:attribute name="class"><xsl:text>on</xsl:text></xsl:attribute>
												</xsl:if>
												<xsl:attribute name="href"><xsl:value-of select="luna:link"/></xsl:attribute>
												<xsl:attribute name="title"><xsl:value-of select="luna:value"/></xsl:attribute>
												<xsl:value-of select="luna:lid"/>
											</a>
										</li>
									</xsl:for-each>
								</ul>
							<!-- </xsl:if>  -->
						</div>
					</div>
				</div>
			</body>
		</html>
	</xsl:template>

</xsl:stylesheet>
