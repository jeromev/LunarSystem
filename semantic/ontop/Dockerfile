# Ontop SPARQL endpoint + the MySQL JDBC driver (not bundled in the base image).
# The driver is fetched at build time so no binary jar is committed to the repo.
FROM ontop/ontop:5.1.2
USER root
ADD https://repo1.maven.org/maven2/com/mysql/mysql-connector-j/8.0.33/mysql-connector-j-8.0.33.jar /opt/ontop/jdbc/mysql-connector-j-8.0.33.jar
