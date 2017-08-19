<?php
declare(strict_types=1);

namespace Roave\BetterReflectionTest\Reflection;

use Roave\BetterReflection\Reflection\Exception\Uncloneable;
use Roave\BetterReflection\Reflection\ReflectionProperty;
use Roave\BetterReflection\Reflector\ClassReflector;
use Roave\BetterReflection\SourceLocator\Type\ComposerSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\SingleFileSourceLocator;
use Roave\BetterReflection\SourceLocator\Type\StringSourceLocator;
use Roave\BetterReflectionTest\Fixture\ClassForHinting;
use phpDocumentor\Reflection\Types;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use Roave\BetterReflectionTest\Fixture\ExampleClass;

/**
 * @covers \Roave\BetterReflection\Reflection\ReflectionProperty
 */
class ReflectionPropertyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClassReflector
     */
    private $reflector;

    public function setUp() : void
    {
        global $loader;
        $this->reflector = new ClassReflector(new ComposerSourceLocator($loader));
    }

    public function testCreateFromName() : void
    {
        $property = ReflectionProperty::createFromName(\ReflectionFunctionAbstract::class, 'name');

        self::assertInstanceOf(ReflectionProperty::class, $property);
        self::assertSame('name', $property->getName());
    }

    public function testCreateFromInstance() : void
    {
        $property = ReflectionProperty::createFromInstance(new ClassForHinting(), 'someProperty');

        self::assertInstanceOf(ReflectionProperty::class, $property);
        self::assertSame('someProperty', $property->getName());
    }

    public function testImplementsReflector() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);
        $publicProp = $classInfo->getProperty('publicProperty');

        self::assertInstanceOf(\Reflector::class, $publicProp);
    }

    public function testVisibilityMethods() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);

        $privateProp = $classInfo->getProperty('privateProperty');
        self::assertTrue($privateProp->isPrivate());

        $protectedProp = $classInfo->getProperty('protectedProperty');
        self::assertTrue($protectedProp->isProtected());

        $publicProp = $classInfo->getProperty('publicProperty');
        self::assertTrue($publicProp->isPublic());
    }

    public function testIsStatic() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);

        $publicProp = $classInfo->getProperty('publicProperty');
        self::assertFalse($publicProp->isStatic());

        $staticProp = $classInfo->getProperty('publicStaticProperty');
        self::assertTrue($staticProp->isStatic());
    }

    /**
     * @return array
     */
    public function stringTypesDataProvider() : array
    {
        return [
            ['privateProperty', ['int', 'float', '\stdClass']],
            ['protectedProperty', ['bool', 'bool[]', 'bool[][]']],
            ['publicProperty', ['string']],
        ];
    }

    /**
     * @param string $propertyName
     * @param string[] $expectedTypes
     * @dataProvider stringTypesDataProvider
     */
    public function testGetDocBlockTypeStrings(string $propertyName, array $expectedTypes) : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);

        $property = $classInfo->getProperty($propertyName);

        self::assertSame($expectedTypes, $property->getDocBlockTypeStrings());
    }

    /**
     * @return array
     */
    public function typesDataProvider() : array
    {
        return [
            ['privateProperty', [Types\Integer::class, Types\Float_::class, Types\Object_::class]],
            ['protectedProperty', [Types\Boolean::class, Types\Array_::class, Types\Array_::class]],
            ['publicProperty', [Types\String_::class]],
        ];
    }

    /**
     * @param string $propertyName
     * @param string[] $expectedTypes
     * @dataProvider typesDataProvider
     */
    public function testGetDocBlockTypes(string $propertyName, array $expectedTypes) : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);

        $foundTypes = $classInfo->getProperty($propertyName)->getDocBlockTypes();

        self::assertCount(\count($expectedTypes), $foundTypes);

        foreach ($expectedTypes as $i => $expectedType) {
            self::assertInstanceOf($expectedType, $foundTypes[$i]);
        }
    }

    public function testGetDocComment() : void
    {
        $expectedDoc = "/**\n * @var string\n */";

        $classInfo = $this->reflector->reflect(ExampleClass::class);
        $property = $classInfo->getProperty('publicProperty');

        self::assertSame($expectedDoc, $property->getDocComment());
    }

    public function testGetDocCommentBetweeenComments() : void
    {
        $php = '<?php
            class Bar implements Foo {
                /* A comment  */
                /** Property description */
                /* An another comment */
                public $prop;
            }
        ';
        $reflector = (new ClassReflector(new StringSourceLocator($php)))->reflect('Bar');
        $property = $reflector->getProperty('prop');

        self::assertContains('Property description', $property->getDocComment());
    }

    public function testGetDocCommentReturnsEmptyStringWithNoComment() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);
        $property = $classInfo->getProperty('publicStaticProperty');

        self::assertSame('', $property->getDocComment());
    }

    public function testExportThrowsException() : void
    {
        $this->expectException(\Exception::class);
        ReflectionProperty::export();
    }

    public function modifierProvider() : array
    {
        return [
            ['publicProperty', \ReflectionProperty::IS_PUBLIC, ['public']],
            ['protectedProperty', \ReflectionProperty::IS_PROTECTED, ['protected']],
            ['privateProperty', \ReflectionProperty::IS_PRIVATE, ['private']],
            ['publicStaticProperty', \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_STATIC, ['public', 'static']],
        ];
    }

    /**
     * @param string $propertyName
     * @param int $expectedModifier
     * @param string[] $expectedModifierNames
     * @dataProvider modifierProvider
     */
    public function testGetModifiers(string $propertyName, int $expectedModifier, array $expectedModifierNames) : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);
        $property = $classInfo->getProperty($propertyName);

        self::assertSame($expectedModifier, $property->getModifiers());
        self::assertSame(
            $expectedModifierNames,
            \Reflection::getModifierNames($property->getModifiers())
        );
    }

    public function testIsDefault() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);

        self::assertTrue($classInfo->getProperty('publicProperty')->isDefault());
        self::assertTrue($classInfo->getProperty('publicStaticProperty')->isDefault());
    }

    public function testIsDefaultWithRuntimeDeclaredProperty() : void
    {
        self::assertFalse(
            ReflectionProperty::createFromNode(
                $this->reflector,
                new Property(Class_::MODIFIER_PUBLIC, [new PropertyProperty('foo')]),
                $this->reflector->reflect(ExampleClass::class),
                false
            )
            ->isDefault()
        );
    }

    public function castToStringProvider() : array
    {
        return [
            ['publicProperty', 'Property [ <default> public $publicProperty ]'],
            ['protectedProperty', 'Property [ <default> protected $protectedProperty ]'],
            ['privateProperty', 'Property [ <default> private $privateProperty ]'],
            ['publicStaticProperty', 'Property [ public static $publicStaticProperty ]'],
        ];
    }

    /**
     * @param string $propertyName
     * @param string $expectedString
     * @dataProvider castToStringProvider
     */
    public function testCastingToString(string $propertyName, string $expectedString) : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);
        self::assertSame($expectedString, (string)$classInfo->getProperty($propertyName));
    }

    public function testGetDefaultProperty() : void
    {
        $classInfo = (new ClassReflector(new SingleFileSourceLocator(__DIR__ . '/../Fixture/DefaultProperties.php')))->reflect('Foo');

        self::assertSame(123, $classInfo->getProperty('hasDefault')->getDefaultValue());
        self::assertNull($classInfo->getProperty('noDefault')->getDefaultValue());
    }

    public function testCannotClone() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);
        $publicProp = $classInfo->getProperty('publicProperty');

        $this->expectException(Uncloneable::class);
        $unused = clone $publicProp;
    }

    public function testSetVisibility() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);
        $publicProp = $classInfo->getProperty('publicStaticProperty');

        self::assertFalse($publicProp->isPrivate(), 'Should initially be public, was private');
        self::assertFalse($publicProp->isProtected(), 'Should initially be public, was protected');
        self::assertTrue($publicProp->isPublic(), 'Should initially be public, was not public');
        self::assertTrue($publicProp->isStatic(), 'Should initially be static');

        $publicProp->setVisibility(\ReflectionProperty::IS_PRIVATE);

        self::assertTrue($publicProp->isPrivate(), 'After setting private, isPrivate is not set');
        self::assertFalse($publicProp->isProtected(), 'After setting private, protected is now set but should not be');
        self::assertFalse($publicProp->isPublic(), 'After setting private, public is still set but should not be');
        self::assertTrue($publicProp->isStatic(), 'Should still be static after setting private');

        $publicProp->setVisibility(\ReflectionProperty::IS_PROTECTED);

        self::assertFalse($publicProp->isPrivate(), 'After setting protected, should no longer be private');
        self::assertTrue($publicProp->isProtected(), 'After setting protected, expect isProtected to be set');
        self::assertFalse($publicProp->isPublic(), 'After setting protected, public is set but should not be');
        self::assertTrue($publicProp->isStatic(), 'Should still be static after setting protected');

        $publicProp->setVisibility(\ReflectionProperty::IS_PUBLIC);

        self::assertFalse($publicProp->isPrivate(), 'After setting public, isPrivate should not be set');
        self::assertFalse($publicProp->isProtected(), 'After setting public, isProtected should not be set');
        self::assertTrue($publicProp->isPublic(), 'After setting public, isPublic should be set but was not');
        self::assertTrue($publicProp->isStatic(), 'Should still be static after setting public');
    }

    public function testSetVisibilityThrowsExceptionWithInvalidArgument() : void
    {
        $classInfo = $this->reflector->reflect(ExampleClass::class);
        $publicProp = $classInfo->getProperty('publicProperty');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Visibility should be \ReflectionProperty::IS_PRIVATE, ::IS_PROTECTED or ::IS_PUBLIC constants');
        $publicProp->setVisibility(0);
    }

    /**
     * @param string $php
     * @param int $startLine
     * @param int $endLine
     * @dataProvider startEndLineProvider
     */
    public function testStartEndLine(string $php, int $startLine, int $endLine) : void
    {
        $reflector = new ClassReflector(new StringSourceLocator($php));
        $classReflection = $reflector->reflect('\T');
        $constReflection = $classReflection->getProperty('test');
        $this->assertEquals($startLine, $constReflection->getStartLine());
        $this->assertEquals($endLine, $constReflection->getEndLine());
    }

    public function startEndLineProvider() : array
    {
        return [
            ["<?php\nclass T {\npublic \$test = 1; }", 3, 3],
            ["<?php\n\nclass T {\npublic \$test = 1; }", 4, 4],
            ["<?php\nclass T {\npublic \$test = \n1; }", 3, 4],
            ["<?php\nclass T {\npublic \n\$test = 1; }", 3, 4],
        ];
    }
}
