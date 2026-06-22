<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet
	version="1.0"
	xmlns="http://www.w3.org/1999/xhtml"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:luna="https://jeromev.github.io/LunarSystem/ontology#" xmlns:schema="https://schema.org/"
	xmlns:dcterms="http://purl.org/dc/terms/"
	xmlns:foaf="http://xmlns.com/foaf/0.1/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
	xmlns:xsd="http://www.w3.org/2001/XMLSchema#"
	xmlns:owl="http://www.w3.org/2002/07/owl#"
	xmlns:dc="http://purl.org/dc/elements/1.1/">

	<xsl:variable name="mod_lid"/>

	<xsl:include href="./luna.header.html.xsl"/>

	<xsl:template name="page">
		<xsl:for-each select="/rdf:RDF/schema:Article[schema:isPartOf/@rdf:resource = /rdf:RDF/schema:WebPage[schema:identifier = $masternodenid]/@rdf:about and substring-before(concat(schema:articleBody/@xml:lang,'-'),'-') = substring-before(concat($lang,'-'),'-')]">
			<div class="box text">
				<xsl:if test="normalize-space(schema:name) != '' and normalize-space(schema:name) != '0'">
					<h2>
						<xsl:attribute name="xml:lang"><xsl:value-of select="schema:name/@xml:lang"/></xsl:attribute>
						<xsl:value-of select="schema:name"/>
					</h2>
				</xsl:if>
				<div class="box-content">
					<xsl:attribute name="xml:lang"><xsl:value-of select="schema:articleBody/@xml:lang"/></xsl:attribute>
					<xsl:value-of select="schema:articleBody" disable-output-escaping="yes"/>
				</div>
			</div>
		</xsl:for-each>
	</xsl:template>

</xsl:stylesheet>
