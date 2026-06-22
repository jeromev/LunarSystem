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

	<xsl:variable name="mod_lid">mod_edit_texts</xsl:variable>

	<xsl:include href="./luna.header.html.xsl"/>
	<xsl:include href="./luna.common_admin.html.xsl"/>

	<xsl:variable name="modify_item_nid"><xsl:value-of select="/rdf:RDF/luna:data[luna:lid = 'modify_item_nid']/luna:value"/></xsl:variable>

	<xsl:template name="page">
		<xsl:if test="$modify_item_nid = ''">
			<div class="box">
				<form method="post" id="Addtext">
					<xsl:attribute name="action"><xsl:value-of select="$pageurl"/></xsl:attribute>
					<fieldset>
						<h2>
							<xsl:attribute name="class">
								<xsl:text>box-handle</xsl:text>
								<xsl:choose>
									<xsl:when test="/rdf:RDF/luna:message[luna:code = 'warning']">
										<xsl:text> expanded</xsl:text>
									</xsl:when>
									<xsl:otherwise>
										<xsl:text> collapsed</xsl:text>
									</xsl:otherwise>
								</xsl:choose>
							</xsl:attribute>
							<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Add a text']/luna:value"/>
						</h2>
						<div>
							<xsl:attribute name="class">
								<xsl:text>box-content</xsl:text>
								<xsl:if test="not(/rdf:RDF/luna:message[luna:code = 'warning'])">
									<xsl:text> off</xsl:text>
								</xsl:if>
							</xsl:attribute>
							<div class="fields">
								<div class="onecol">
									<xsl:call-template name="forminput">
										<xsl:with-param name="type">checkbox</xsl:with-param>
										<xsl:with-param name="name">add_text_is_inactive</xsl:with-param>
										<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Deactivate']/luna:value"/></xsl:with-param>
									</xsl:call-template>
								</div>
								<div class="col">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">add_text_lid</xsl:with-param>
										<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Literal identifier']/luna:value"/></xsl:with-param>
									</xsl:call-template>
								</div>
								<div class="col autowidth">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">add_text_lang</xsl:with-param>
										<xsl:with-param name="type">select</xsl:with-param>
										<xsl:with-param name="mode">data</xsl:with-param>
										<xsl:with-param name="foreach" select="/rdf:RDF/luna:lang"/>
										<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Language']/luna:value"/></xsl:with-param>
										<xsl:with-param name="default-value" select="/rdf:RDF/luna:lang[luna:selected = '1']/luna:lid"/>
									</xsl:call-template>
								</div>
								<div class="onecol">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">add_text_title</xsl:with-param>
										<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'title']/luna:value"/></xsl:with-param>
										<xsl:with-param name="class">big</xsl:with-param>
									</xsl:call-template>
									<br />
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">add_text_content</xsl:with-param>
										<xsl:with-param name="type">textarea</xsl:with-param>
										<xsl:with-param name="label" select="/rdf:RDF/luna:vocabulary[luna:lid = 'content']/luna:value"/>
										<xsl:with-param name="class">big</xsl:with-param>
										<xsl:with-param name="wysiwyg">1</xsl:with-param>
										<xsl:with-param name="default-value"></xsl:with-param>
									</xsl:call-template>
								</div>
								<div class="col">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">add_text_pages</xsl:with-param>
										<xsl:with-param name="type">select</xsl:with-param>
										<xsl:with-param name="foreach" select="/rdf:RDF/luna:page"/>
										<xsl:with-param name="label" select="/rdf:RDF/luna:vocabulary[luna:lid = 'Pages using the text']/luna:value"/>
										<xsl:with-param name="option-value">name</xsl:with-param>
										<xsl:with-param name="multiple">yes</xsl:with-param>
										<xsl:with-param name="size" select="count(/rdf:RDF/luna:page)"/>
									</xsl:call-template>
								</div>
							</div>
							<div class="submit">
								<input type="hidden" name="mode" value="add"/>
								<input type="submit" class="submit" name="submit">
									<xsl:attribute name="value"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Add']/luna:value"/></xsl:attribute>
								</input>
							</div>
						</div>
					</fieldset>
					<xsl:call-template name="csrf-input"/>
				</form>
			</div>
			<xsl:call-template name="textslist"/>
		</xsl:if>
		<xsl:if test="not($modify_item_nid = '')">
			<div class="box">
				<form method="post" id="Modifypage">
					<xsl:attribute name="action"><xsl:value-of select="$pageurl"/></xsl:attribute>
					<fieldset>
						<h2 class="box-handle expanded">
							<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Modify the text']/luna:value"/>
							<xsl:text> </xsl:text>
							<em><xsl:value-of select="/rdf:RDF/luna:text[schema:identifier = $modify_item_nid]/luna:lid"/></em>
						</h2>
						<div class="box-content">
							<div class="onecol">
								<xsl:call-template name="forminput">
									<xsl:with-param name="type">checkbox</xsl:with-param>
									<xsl:with-param name="name">modify_text_is_inactive</xsl:with-param>
									<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Deactivate']/luna:value"/></xsl:with-param>
									<xsl:with-param name="default-value">
										<xsl:choose>
											<xsl:when test="/rdf:RDF/luna:text[schema:identifier = $modify_item_nid]/luna:isActive = '1'">
												<xsl:text>0</xsl:text>
											</xsl:when>
											<xsl:otherwise>
												<xsl:text>1</xsl:text>
											</xsl:otherwise>
										</xsl:choose>
									</xsl:with-param>
								</xsl:call-template>
							</div>
							<div class="fields">
								<div class="col">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">modify_text_lid</xsl:with-param>
										<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Literal identifier']/luna:value"/></xsl:with-param>
										<xsl:with-param name="default-value"><xsl:value-of select="/rdf:RDF/luna:text[schema:identifier = $modify_item_nid]/luna:lid"/></xsl:with-param>
									</xsl:call-template>
								</div>
								<div class="col">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">modify_text_lang</xsl:with-param>
										<xsl:with-param name="type">select</xsl:with-param>
										<xsl:with-param name="mode">data</xsl:with-param>
										<xsl:with-param name="foreach" select="/rdf:RDF/luna:lang"/>
										<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Language']/luna:value"/></xsl:with-param>
										<xsl:with-param name="default-value" select="/rdf:RDF/luna:text[schema:identifier = $modify_item_nid]/schema:name/@xml:lang"/>
									</xsl:call-template>
								</div>
								<div class="onecol">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">modify_text_title</xsl:with-param>
										<xsl:with-param name="label"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'title']/luna:value"/></xsl:with-param>
										<xsl:with-param name="class">big</xsl:with-param>
										<xsl:with-param name="default-value"><xsl:value-of select="/rdf:RDF/luna:text[schema:identifier = $modify_item_nid]/schema:name"/></xsl:with-param>
									</xsl:call-template>
									<br />
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">modify_text_content</xsl:with-param>
										<xsl:with-param name="type">textarea</xsl:with-param>
										<xsl:with-param name="label" select="/rdf:RDF/luna:vocabulary[luna:lid = 'content']/luna:value"/>
										<xsl:with-param name="class">big</xsl:with-param>
										<xsl:with-param name="wysiwyg">1</xsl:with-param>
										<xsl:with-param name="default-value"><xsl:value-of select="/rdf:RDF/luna:text/schema:articleBody"/></xsl:with-param>
									</xsl:call-template>
								</div>
								<div class="col">
									<xsl:call-template name="forminput">
										<xsl:with-param name="name">modify_text_pages</xsl:with-param>
										<xsl:with-param name="type">select</xsl:with-param>
										<xsl:with-param name="foreach" select="/rdf:RDF/luna:page"/>
										<xsl:with-param name="label" select="/rdf:RDF/luna:vocabulary[luna:lid = 'Pages using the text']/luna:value"/>
										<xsl:with-param name="option-value">name</xsl:with-param>
										<xsl:with-param name="multiple">yes</xsl:with-param>
										<xsl:with-param name="size" select="count(/rdf:RDF/luna:page)"/>
										<xsl:with-param name="default-value" select="/rdf:RDF/luna:text[schema:identifier = $modify_item_nid]/luna:page"/>
									</xsl:call-template>
								</div>
							</div>
							<div class="submit">
								<input type="hidden" name="mode" value="modify"/>
								<input type="hidden" name="text_nid">
									<xsl:attribute name="value"><xsl:value-of select="$modify_item_nid"/></xsl:attribute>
								</input>
								<input type="hidden" name="modify_item_nid">
									<xsl:attribute name="value"><xsl:value-of select="$modify_item_nid"/></xsl:attribute>
								</input>
								<input type="submit" class="submit" name="submit">
									<xsl:attribute name="value"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Modify']/luna:value"/></xsl:attribute>
								</input>
								<input type="submit" class="submit warning" name="submit">
									<xsl:attribute name="value"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Delete']/luna:value"/></xsl:attribute>
									<xsl:attribute name="data-confirm">

										<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Are you sure you want to delete this text?']/luna:value"/>

									</xsl:attribute>
								</input>
							</div>
						</div>
					</fieldset>
					<xsl:call-template name="csrf-input"/>
				</form>
			</div>
		</xsl:if>
	</xsl:template>

</xsl:stylesheet>
