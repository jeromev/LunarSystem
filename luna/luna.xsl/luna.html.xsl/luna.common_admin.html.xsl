<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet
	version="1.0"
	xmlns="http://www.w3.org/1999/xhtml"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:luna="https://jeromev.github.io/LunarSystem/ontology#"
	xmlns:dcterms="http://purl.org/dc/terms/"
	xmlns:foaf="http://xmlns.com/foaf/0.1/"
	xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
	xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
	xmlns:xsd="http://www.w3.org/2001/XMLSchema#"
	xmlns:owl="http://www.w3.org/2002/07/owl#"
	xmlns:dc="http://purl.org/dc/elements/1.1/">

	<xsl:template name="online_users">
		<xsl:param name="expand">1</xsl:param>
		<div id="OnlineUsers" class="box">
			<xsl:variable name="mod_nid"><xsl:value-of select="/rdf:RDF/luna:mod[luna:lid = 'mod_online_users']/luna:nid"/></xsl:variable>
			<h2>
				<xsl:attribute name="class">
					<xsl:text>box-handle</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text> expanded</xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> collapsed</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:value-of select="/rdf:RDF/luna:mod[luna:nid = $mod_nid]/rdfs:label"/>
			</h2>
			<div>
				<xsl:attribute name="class">
					<xsl:text>box-content boxtable</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text></xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> off</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<table class="zebra">
					<caption class="off"><xsl:value-of select="/rdf:RDF/luna:mod[luna:nid = $mod_nid]/rdfs:label"/></caption>
					<thead>
						<tr>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'firstname']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'lastname']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'email']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'IP']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'session_url']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'last_time']/luna:value"/></th>
						</tr>
					</thead>
					<tbody>
						<xsl:variable name="admin_users_url" select="/rdf:RDF/luna:page[luna:lid = 'admin_users']/luna:alias"/>
						<xsl:for-each select="/rdf:RDF/foaf:Person">
							<xsl:variable name="user_nid" select="luna:nid"/>
							<tr>
								<td><xsl:value-of select="foaf:firstName"/></td>
								<td><xsl:value-of select="foaf:surName"/></td>
								<td>
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:lid = 'admin_users']/luna:alias"/>
												<xsl:with-param name="options">
													<xsl:text>user_nid=</xsl:text>
													<xsl:value-of select="luna:nid"/>
												</xsl:with-param>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:call-template name="cutstring">
											<xsl:with-param name="string">
												<xsl:call-template name="cutstring">
													<xsl:with-param name="string"><xsl:value-of select="foaf:mbox/@rdf:resource"/></xsl:with-param>
													<xsl:with-param name="before" select="1"/>
													<xsl:with-param name="length" select="8"/>
												</xsl:call-template>
											</xsl:with-param>
										</xsl:call-template>
									</a>
								</td>
								<td><xsl:value-of select="luna:ip"/></td>
								<td class="nowrap">
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="luna:url"/>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:attribute name="title">
											<xsl:value-of select="luna:url"/>
										</xsl:attribute>
										<xsl:text>/</xsl:text>
										<xsl:call-template name="cutstring">
											<xsl:with-param name="string"><xsl:value-of select="luna:url"/></xsl:with-param>
										</xsl:call-template>
									</a>
								</td>
								<td class="nowrap"><xsl:value-of select="luna:last-visit"/></td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</div>
		</div>
	</xsl:template>

	<xsl:template name="modslist">
		<xsl:param name="expand">1</xsl:param>
		<div id="ModsList" class="box">
			<h2>
				<xsl:attribute name="class">
					<xsl:text>box-handle</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text> expanded</xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> collapsed</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Modules']/luna:value"/>
			</h2>
			<div>
				<xsl:attribute name="class">
					<xsl:text>box-content boxtable</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text></xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> off</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<table class="zebra">
					<caption class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Modules']/luna:value"/></caption>
					<thead>
						<tr>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'name']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Access level']/luna:value"/></th>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="/rdf:RDF/luna:mod">
							<xsl:sort select="rdfs:label"/>
							<tr>
								<xsl:attribute name="class">
									<xsl:text>active</xsl:text>
									<xsl:if test="not(luna:is_active = '1')">
										<xsl:text> inactive</xsl:text>
									</xsl:if>
								</xsl:attribute>
								<xsl:attribute name="data-href">

									<xsl:call-template name="link">
										<xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/luna:alias"/>
										<xsl:with-param name="options">
											<xsl:text>mod_nid=</xsl:text><xsl:value-of select="luna:nid"/>
										</xsl:with-param>
									</xsl:call-template>
									<xsl:call-template name="onchange3"/>

								</xsl:attribute>
								<td>
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/luna:alias"/>
												<xsl:with-param name="options">
													<xsl:text>mod_nid=</xsl:text><xsl:value-of select="luna:nid"/>
												</xsl:with-param>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:value-of select="rdfs:label"/>
									</a>
								</td>
								<td>
									<xsl:variable name="level-resource" select="luna:level/@rdf:resource"/>
									<xsl:value-of select="/rdf:RDF/luna:level[@rdf:about = $level-resource]/rdfs:label"/>
								</td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</div>
		</div>
	</xsl:template>

	<xsl:template name="textslist">
		<xsl:param name="expand">1</xsl:param>
		<xsl:param name="modpagealias">
			<xsl:value-of select="/rdf:RDF/luna:page[luna:lid = 'edit_texts']/luna:alias"/>
		</xsl:param>
		<div id="TextsList" class="box">
			<h2>
				<xsl:attribute name="class">
					<xsl:text>box-handle</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text> expanded</xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> collapsed</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Texts']/luna:value"/>
			</h2>
			<div>
				<xsl:attribute name="class">
					<xsl:text>box-content boxtable</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text></xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> off</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<table class="zebra">
					<caption class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Texts']/luna:value"/></caption>
					<thead>
						<tr>
							<td colspan="5">
								<xsl:call-template name="pager"/>
							</td>
						</tr>
						<tr>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=lid&amp;order_dir=ASC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'lid'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Literal identifier']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Literal identifier']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=title&amp;order_dir=ASC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'title'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'title']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'title']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=lang&amp;order_dir=ASC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'lang'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'lang']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'lang']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=last_time&amp;order_dir=DESC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'last_time'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'modified']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'modified']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="/rdf:RDF/luna:text">
							<tr>
								<xsl:attribute name="class">
									<xsl:text>active</xsl:text>
									<xsl:if test="not(luna:is_active = '1')">
										<xsl:text> inactive</xsl:text>
									</xsl:if>
								</xsl:attribute>
								<xsl:attribute name="data-href">

									<xsl:call-template name="link">
										<xsl:with-param name="alias" select="$modpagealias"/>
										<xsl:with-param name="options">
											<xsl:text>text_nid=</xsl:text>
											<xsl:value-of select="luna:nid"/>
										</xsl:with-param>
									</xsl:call-template>
									<xsl:call-template name="onchange4"/>

								</xsl:attribute>
								<td>
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="$modpagealias"/>
												<xsl:with-param name="options">
													<xsl:text>text_nid=</xsl:text>
													<xsl:value-of select="luna:nid"/>
												</xsl:with-param>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:value-of select="luna:lid"/>
									</a>
								</td>
								<td><xsl:value-of select="rdfs:label"/></td>
								<td><xsl:value-of select="rdfs:label/@xml:lang"/></td>
								<td class="nowrap"><xsl:value-of select="luna:save_time"/></td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</div>
		</div>
	</xsl:template>

	<xsl:template name="userslist">
		<xsl:param name="expand">1</xsl:param>
		<xsl:param name="modpagealias">
			<xsl:value-of select="/rdf:RDF/luna:page[luna:lid = 'admin_users']/luna:alias"/>
		</xsl:param>
		<div id="UsersList" class="box">
			<h2>
				<xsl:attribute name="class">
					<xsl:text>box-handle</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text> expanded</xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> collapsed</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Users']/luna:value"/>
			</h2>
			<div>
				<xsl:attribute name="class">
					<xsl:text>box-content boxtable</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text></xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> off</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<table class="zebra">
					<caption class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Users']/luna:value"/></caption>
					<thead>
						<tr>
							<td colspan="5">
								<xsl:call-template name="pager"/>
							</td>
						</tr>
						<tr>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=firstname&amp;order_dir=ASC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'firstname'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'firstname']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'firstname']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=lastname&amp;order_dir=ASC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'lastname'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'lastname']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'lastname']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=email&amp;order_dir=ASC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'email'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'email']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'email']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=regis_time&amp;order_dir=DESC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'regis_time'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'regis_time']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'regis_time']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
							<th>
								<a>
									<xsl:attribute name="href">
										<xsl:call-template name="link">
											<xsl:with-param name="alias" select="$modpagealias"/>
											<xsl:with-param name="options">
												<xsl:text>order_by=last_time&amp;order_dir=DESC&amp;start=0</xsl:text>
											</xsl:with-param>
										</xsl:call-template>
									</xsl:attribute>
									<xsl:choose>
										<xsl:when test="/rdf:RDF/luna:data[luna:lid = 'order_by']/luna:value = 'last_time'">
											<strong><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'last_time']/luna:value"/></strong>
										</xsl:when>
										<xsl:otherwise>
											<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'last_time']/luna:value"/>
										</xsl:otherwise>
									</xsl:choose>
								</a>
							</th>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="/rdf:RDF/foaf:Person">
							<tr>
								<xsl:attribute name="class">
									<xsl:text>active</xsl:text>
									<xsl:if test="not(luna:is_active = '1')">
										<xsl:text> inactive</xsl:text>
									</xsl:if>
								</xsl:attribute>
								<xsl:attribute name="data-href">

									<xsl:call-template name="link">
										<xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/luna:alias"/>
										<xsl:with-param name="options">
											<xsl:text>user_nid=</xsl:text>
											<xsl:value-of select="luna:nid"/>
										</xsl:with-param>
									</xsl:call-template>
									<xsl:call-template name="onchange4"/>

								</xsl:attribute>
								<td><xsl:value-of select="foaf:firstName"/></td>
								<td><xsl:value-of select="foaf:surName"/></td>
								<td>
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="/rdf:RDF/luna:page[luna:nid = $masternodenid]/luna:alias"/>
												<xsl:with-param name="options">
													<xsl:text>user_nid=</xsl:text>
													<xsl:value-of select="luna:nid"/>
												</xsl:with-param>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:call-template name="cutstring">
											<xsl:with-param name="string">
												<xsl:value-of select="foaf:mbox/@rdf:resource"/>
											</xsl:with-param>
											<xsl:with-param name="length">8</xsl:with-param>
											<xsl:with-param name="before">1</xsl:with-param>
										</xsl:call-template>
									</a>
								</td>
								<td class="nowrap"><xsl:value-of select="luna:registration-date"/></td>
								<td class="nowrap"><xsl:value-of select="luna:last-visit"/></td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</div>
		</div>
	</xsl:template>

	<xsl:template name="pageslist">
		<xsl:param name="expand">1</xsl:param>
		<xsl:param name="modpagealias">
			<xsl:value-of select="/rdf:RDF/luna:page[luna:lid = 'admin_pages']/luna:alias"/>
		</xsl:param>
		<div id="PagesList" class="box">
			<h2>
				<xsl:attribute name="class">
					<xsl:text>box-handle</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'"><xsl:text> expanded</xsl:text></xsl:when>
						<xsl:otherwise><xsl:text> collapsed</xsl:text></xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Pages']/luna:value"/>
			</h2>
			<div>
				<xsl:attribute name="class">
					<xsl:text>box-content boxtable</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'"><xsl:text></xsl:text></xsl:when>
						<xsl:otherwise><xsl:text> off</xsl:text></xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<table class="zebra">
					<caption class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Pages']/luna:value"/></caption>
					<thead>
						<tr>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'name']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Access level']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Modules']/luna:value"/></th>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="/rdf:RDF/luna:page">
							<xsl:sort select="rdfs:label"/>
							<tr>
								<xsl:attribute name="class">
									<xsl:text>active</xsl:text>
									<xsl:if test="not(luna:is_active = '1')">
										<xsl:text> inactive</xsl:text>
									</xsl:if>
								</xsl:attribute>
								<xsl:attribute name="data-href">

									<xsl:call-template name="link">
										<xsl:with-param name="alias" select="$modpagealias"/>
										<xsl:with-param name="options">
											<xsl:text>page_nid=</xsl:text><xsl:value-of select="luna:nid"/>
										</xsl:with-param>
									</xsl:call-template>
									<xsl:call-template name="onchange3"/>

								</xsl:attribute>
								<td>
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="$modpagealias"/>
												<xsl:with-param name="options">
													<xsl:text>page_nid=</xsl:text><xsl:value-of select="luna:nid"/>
												</xsl:with-param>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:value-of select="rdfs:label"/>
									</a>
								</td>
								<td>
									<xsl:variable name="level-resource" select="luna:level/@rdf:resource"/>
									<xsl:value-of select="/rdf:RDF/luna:level[@rdf:about = $level-resource]/rdfs:label"/>
								</td>
								<td>
									<xsl:for-each select="luna:mod">
										<xsl:sort select="@rdf:resource"/>
										<xsl:variable name="resource" select="@rdf:resource"/>
										<xsl:value-of select="/rdf:RDF/luna:mod[@rdf:about = $resource]/rdfs:label"/>
										<xsl:if test="not(position() = last())">
											<xsl:text>, </xsl:text>
										</xsl:if>
									</xsl:for-each>
								</td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</div>
		</div>
	</xsl:template>

	<xsl:template name="groupslist">
		<xsl:param name="expand">1</xsl:param>
		<xsl:param name="modpagealias">
			<xsl:value-of select="/rdf:RDF/luna:page[luna:lid = 'admin_groups']/luna:alias"/>
		</xsl:param>
		<div id="GroupsList" class="box">
			<h2>
				<xsl:attribute name="class">
					<xsl:text>box-handle</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text> expanded</xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> collapsed</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Groups']/luna:value"/>
			</h2>
			<div>
				<xsl:attribute name="class">
					<xsl:text>box-content boxtable</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text></xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> off</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<table class="zebra">
					<caption class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Groups']/luna:value"/></caption>
					<thead>
						<tr>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'name']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Accessible levels']/luna:value"/></th>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="/rdf:RDF/luna:group">
							<xsl:sort select="rdfs:label"/>
							<tr>
								<xsl:attribute name="class">
									<xsl:text>active</xsl:text>
									<xsl:if test="not(luna:is_active = '1')">
										<xsl:text> inactive</xsl:text>
									</xsl:if>
								</xsl:attribute>
								<xsl:attribute name="data-href">

									<xsl:call-template name="link">
										<xsl:with-param name="alias" select="$modpagealias"/>
										<xsl:with-param name="options">
											<xsl:text>group_nid=</xsl:text><xsl:value-of select="luna:nid"/>
										</xsl:with-param>
									</xsl:call-template>
									<xsl:call-template name="onchange3"/>

								</xsl:attribute>
								<td class="nowrap">
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="$modpagealias"/>
												<xsl:with-param name="options">
													<xsl:text>group_nid=</xsl:text><xsl:value-of select="luna:nid"/>
												</xsl:with-param>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:value-of select="rdfs:label"/>
									</a>
								</td>
								<td>
									<xsl:for-each select="luna:level">
										<xsl:sort select="@rdf:resource"/>
										<xsl:variable name="res" select="@rdf:resource"/>
										<xsl:value-of select="/rdf:RDF/luna:level[@rdf:about = $res]/rdfs:label"/>
										<xsl:if test="not(position() = last())">
											<xsl:text>, </xsl:text>
										</xsl:if>
									</xsl:for-each>
								</td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</div>
		</div>
	</xsl:template>

	<xsl:template name="levelslist">
		<xsl:param name="expand">1</xsl:param>
		<xsl:param name="modpagealias">
			<xsl:value-of select="/rdf:RDF/luna:page[luna:lid = 'admin_levels']/luna:alias"/>
		</xsl:param>
		<div id="LevelsList" class="box">
			<h2>
				<xsl:attribute name="class">
					<xsl:text>box-handle</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text> expanded</xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> collapsed</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Levels']/luna:value"/>
			</h2>
			<div>
				<xsl:attribute name="class">
					<xsl:text>box-content boxtable</xsl:text>
					<xsl:choose>
						<xsl:when test="$expand = '1'">
							<xsl:text></xsl:text>
						</xsl:when>
						<xsl:otherwise>
							<xsl:text> off</xsl:text>
						</xsl:otherwise>
					</xsl:choose>
				</xsl:attribute>
				<table class="zebra">
					<caption class="off"><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Levels']/luna:value"/></caption>
					<thead>
						<tr>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'name']/luna:value"/></th>
							<th><xsl:value-of select="/rdf:RDF/luna:vocabulary[luna:lid = 'Groups admitted to this level']/luna:value"/></th>
						</tr>
					</thead>
					<tbody>
						<xsl:for-each select="/rdf:RDF/luna:level">
							<xsl:sort select="rdfs:label"/>
							<tr>
								<xsl:attribute name="class">
									<xsl:text>active</xsl:text>
									<xsl:if test="not(luna:is_active = '1')">
										<xsl:text> inactive</xsl:text>
									</xsl:if>
								</xsl:attribute>
								<xsl:attribute name="data-href">

									<xsl:call-template name="link">
										<xsl:with-param name="alias" select="$modpagealias"/>
										<xsl:with-param name="options">
											<xsl:text>level_nid=</xsl:text><xsl:value-of select="luna:nid"/>
										</xsl:with-param>
									</xsl:call-template>
									<xsl:call-template name="onchange3"/>

								</xsl:attribute>
								<td class="nowrap">
									<a>
										<xsl:attribute name="href">
											<xsl:call-template name="link">
												<xsl:with-param name="alias" select="$modpagealias"/>
												<xsl:with-param name="options">
													<xsl:text>level_nid=</xsl:text><xsl:value-of select="luna:nid"/>
												</xsl:with-param>
											</xsl:call-template>
										</xsl:attribute>
										<xsl:value-of select="rdfs:label"/>
									</a>
								</td>
								<td>
									<xsl:for-each select="luna:group">
										<xsl:sort select="/rdf:RDF/luna:group[@rdf:about = @rdf:resource]/luna:lid"/>
										<xsl:variable name="resource" select="@rdf:resource"/>
										<xsl:value-of select="/rdf:RDF/luna:group[@rdf:about = $resource]/rdfs:label"/>
										<xsl:if test="not(position() = last())">
											<xsl:text>, </xsl:text>
										</xsl:if>
									</xsl:for-each>
								</td>
							</tr>
						</xsl:for-each>
					</tbody>
				</table>
			</div>
		</div>
	</xsl:template>

</xsl:stylesheet>
