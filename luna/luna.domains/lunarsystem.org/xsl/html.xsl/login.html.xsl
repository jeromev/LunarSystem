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
		<form method="post">
			<xsl:attribute name="action"><xsl:value-of select="$pageurl"/></xsl:attribute>
			<div class="box">
				<fieldset id="Login">
					<h2 class="box-handle expanded"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Connection form']/luna:value"/></h2>
					<div class="box-content">
						<div class="fields">
							<div class="col">
								<xsl:call-template name="forminput">
									<xsl:with-param name="name">email</xsl:with-param>
									<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Email']/luna:value"/></xsl:with-param>
								</xsl:call-template>
								<br />
								<xsl:call-template name="forminput">
									<xsl:with-param name="name">password</xsl:with-param>
									<xsl:with-param name="type">password</xsl:with-param>
									<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Password']/luna:value"/></xsl:with-param>
								</xsl:call-template>
								<br />
								<input type="hidden" name="last_url">
									<xsl:attribute name="value">
										<xsl:value-of select="/rdf:RDF/luna:data[luna:lid = 'last_url']/luna:value"/>
									</xsl:attribute>
								</input>
							</div>
						</div>
						<div class="submit">
							<input type="submit" class="submit" name="submit" />
						</div>
					</div>
				</fieldset>
			</div>
		</form>
	</xsl:template>

</xsl:stylesheet>
