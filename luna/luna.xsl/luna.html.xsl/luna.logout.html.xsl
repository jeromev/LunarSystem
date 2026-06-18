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

	<xsl:variable name="mod_lid">mod_log</xsl:variable>

	<xsl:include href="./luna.header.html.xsl"/>

	<xsl:template name="page">
		<p>
			<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'You are now disconnected.']/luna:value"/>
		</p>
	</xsl:template>

</xsl:stylesheet>
